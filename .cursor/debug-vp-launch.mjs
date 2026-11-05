import { spawn } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

const require = createRequire(import.meta.url)
const Module = require('module')
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const bin = path.join(root, 'node_modules', 'vite-plus', 'bin', 'vp')
const linkPath = path.join(root, 'node_modules', 'vite-plus')
const pnpmBin = path.join(
    root,
    'node_modules',
    '.pnpm',
    'vite-plus@0.3.0_@types+node_91d0485bd8723ccbcee7b6b68bdc133b',
    'node_modules',
    'vite-plus',
    'bin',
    'vp',
)

// #region agent log
async function agentLog(hypothesisId, message, data) {
    const payload = {
        sessionId: '373699',
        runId: 'pre-fix',
        hypothesisId,
        location: '.cursor/debug-vp-launch.mjs',
        message,
        data,
        timestamp: Date.now(),
    }
    try {
        await fetch('http://127.0.0.1:7914/ingest/e66699f4-b3b7-440d-b82c-30537e1be9fc', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Debug-Session-Id': '373699',
            },
            body: JSON.stringify(payload),
        })
    } catch {
        // ingest unavailable
    }
}
// #endregion

let statError = null
let size = null
let linkTarget = null
try {
    size = fs.statSync(bin).size
} catch (error) {
    statError = error.code ?? String(error)
}
try {
    linkTarget = fs.readlinkSync(linkPath)
} catch (error) {
    linkTarget = error.code ?? null
}

let findPath = null
try {
    findPath = Module._findPath(bin, null, true)
} catch (error) {
    findPath = `error:${error.code ?? error.message}`
}

let packageType = null
try {
    packageType = JSON.parse(
        fs.readFileSync(path.join(root, 'node_modules', 'vite-plus', 'package.json'), 'utf8'),
    ).type ?? null
} catch (error) {
    packageType = `error:${error.code ?? error.message}`
}

// #region agent log
await agentLog('A', 'vp bin stat', {
    exists: fs.existsSync(bin),
    size,
    statError,
    linkTarget,
    pnpmBinExists: fs.existsSync(pnpmBin),
})
await agentLog('B', 'node main resolution', {
    findPath,
    packageType,
    node: process.version,
})
await agentLog('C', 'local shim', {
    cmd: fs.existsSync(path.join(root, 'node_modules', '.bin', 'vp.cmd')),
    ps1: fs.existsSync(path.join(root, 'node_modules', '.bin', 'vp.ps1')),
})
await agentLog('E', 'node options', {
    nodeOptions: process.env.NODE_OPTIONS ?? null,
    execPath: process.execPath,
})
// #endregion

const child = spawn('vp', ['dev', ...process.argv.slice(2)], {
    cwd: root,
    shell: true,
    env: {
        ...process.env,
        PATH: `${path.join(root, 'node_modules', '.bin')}${path.delimiter}${process.env.PATH ?? ''}`,
    },
    stdio: ['inherit', 'pipe', 'pipe'],
})

let sawBanner = false
let sawMissing = false
let stderrTail = ''

function watch(chunk, stream) {
    const text = chunk.toString()
    stream.write(text)
    const bannerNow = text.includes('VITE+') || text.includes('Local:')
    const missingNow = text.includes('MODULE_NOT_FOUND') || text.includes('Cannot find module')
    if (bannerNow && !sawBanner) {
        sawBanner = true
        // #region agent log
        agentLog('A', 'vp dev banner', { sawBanner: true })
        // #endregion
    }
    if (missingNow && !sawMissing) {
        sawMissing = true
        // #region agent log
        agentLog('B', 'vp reported missing module', { excerpt: text.slice(0, 300) })
        // #endregion
    }
    stderrTail = (stderrTail + text).slice(-500)
}

child.stdout.on('data', (chunk) => watch(chunk, process.stdout))
child.stderr.on('data', (chunk) => watch(chunk, process.stderr))

child.on('exit', (code, signal) => {
    // #region agent log
    agentLog('A', 'vp child exit', {
        code,
        signal,
        sawBanner,
        sawMissing,
        stderrTail,
    }).finally(() => {
        process.exit(code ?? 1)
    })
    // #endregion
})
