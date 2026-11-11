import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-full border px-2.5 py-0.5 font-mono text-[10px] leading-4 font-medium tracking-[0.14em] uppercase w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1.5 [&>svg]:pointer-events-none focus-ring aria-invalid:border-destructive transition-colors overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "border-primary/20 bg-primary/[0.08] text-primary dark:border-primary/40 dark:bg-primary/20 dark:text-primary-foreground [a&]:hover:bg-primary/15",
        secondary:
          "border-transparent bg-secondary text-secondary-foreground [a&]:hover:bg-foreground/10",
        destructive:
          "border-destructive/25 bg-destructive/[0.08] text-destructive-foreground dark:bg-destructive/25 [a&]:hover:bg-destructive/15",
        outline:
          "border-foreground/20 text-foreground [a&]:hover:bg-foreground [a&]:hover:text-background",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
