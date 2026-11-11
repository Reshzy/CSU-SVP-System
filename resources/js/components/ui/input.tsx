import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "file:text-foreground flex h-9 w-full min-w-0 rounded-md border bg-card px-3 py-1 text-base file:mr-3 file:inline-flex file:h-7 file:border-0 file:bg-transparent file:font-mono file:text-[11px] file:font-medium file:tracking-[0.12em] file:uppercase disabled:pointer-events-none md:text-sm",
        "border-input placeholder:text-muted-foreground/80 hover:border-foreground/30 focus-visible:border-foreground/50 focus-ring aria-invalid:border-destructive transition-[color,border-color] disabled:cursor-not-allowed disabled:opacity-50",
        className
      )}
      {...props}
    />
  )
}

export { Input }
