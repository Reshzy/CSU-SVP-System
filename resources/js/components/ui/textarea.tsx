import * as React from "react"

import { cn } from "@/lib/utils"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "flex field-sizing-content min-h-20 w-full rounded-md border bg-card px-3 py-2 text-base leading-relaxed md:text-sm",
        "border-input placeholder:text-muted-foreground/80 hover:border-foreground/30 focus-visible:border-foreground/50 focus-ring aria-invalid:border-destructive transition-[color,border-color] disabled:cursor-not-allowed disabled:opacity-50",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
