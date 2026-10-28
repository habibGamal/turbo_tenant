import * as React from "react"
import { toast, Toaster as Sonner } from "sonner"

type ToasterProps = React.ComponentProps<typeof Sonner>

const Toaster = ({ dir: propDir, ...props }: ToasterProps) => {
  const [theme, setTheme] = React.useState<"light" | "dark">(() => {
    if (typeof document !== "undefined") {
      return document.documentElement.classList.contains("dark") ? "dark" : "light"
    }
    return "light"
  })

  const [dir, setDir] = React.useState<"rtl" | "ltr" | "auto">(() => {
    if (typeof document !== "undefined") {
      return (document.documentElement.dir as "rtl" | "ltr") || "rtl"
    }
    return "rtl"
  })

  React.useEffect(() => {
    if (typeof document === "undefined") return

    const checkAttributes = () => {
      setTheme(document.documentElement.classList.contains("dark") ? "dark" : "light")
      const currentDir = document.documentElement.dir as "rtl" | "ltr"
      if (currentDir) {
        setDir(currentDir)
      }
    }

    const observer = new MutationObserver(checkAttributes)
    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["class", "dir"],
    })

    return () => observer.disconnect()
  }, [])

  return (
    <Sonner
      theme={theme}
      dir={dir}
      className="toaster group"
      toastOptions={{
        classNames: {
          toast:
            "group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg",
          description: "group-[.toast]:text-muted-foreground",
          actionButton:
            "group-[.toast]:bg-primary group-[.toast]:text-primary-foreground",
          cancelButton:
            "group-[.toast]:bg-muted group-[.toast]:text-muted-foreground",
        },
      }}
      {...props}
    />
  )
}

export { Toaster, toast }
