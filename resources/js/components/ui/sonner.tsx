import * as React from "react"
import { useTranslation } from "react-i18next"
import { toast, Toaster as Sonner } from "sonner"

type ToasterProps = React.ComponentProps<typeof Sonner>

const Toaster = ({
  dir: propDir,
  toastOptions,
  mobileOffset,
  containerAriaLabel,
  ...props
}: ToasterProps) => {
  const { t } = useTranslation()
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

  const resolvedDir = propDir ?? dir

  return (
    <Sonner
      theme={theme}
      dir={resolvedDir}
      className="toaster group"
      closeButton
      containerAriaLabel={containerAriaLabel ?? t("notificationsRegion", "Notifications")}
      mobileOffset={mobileOffset ?? { bottom: "4.75rem", left: "1rem", right: "1rem" }}
      toastOptions={{
        ...toastOptions,
        closeButtonAriaLabel:
          toastOptions?.closeButtonAriaLabel ?? t("closeNotification", "Close notification"),
        classNames: {
          toast:
            "group toast group-[.toaster]:bg-card group-[.toaster]:text-card-foreground group-[.toaster]:border-border group-[.toaster]:shadow-2xl group-[.toaster]:rounded-2xl group-[.toaster]:p-4 group-[.toaster]:gap-3",
          title: "group-[.toast]:font-semibold group-[.toast]:text-sm group-[.toast]:text-foreground",
          description: "group-[.toast]:text-muted-foreground group-[.toast]:text-xs",
          actionButton:
            "group-[.toast]:bg-primary group-[.toast]:text-primary-foreground group-[.toast]:font-semibold group-[.toast]:text-sm group-[.toast]:rounded-xl group-[.toast]:px-4 group-[.toast]:h-10 group-[.toast]:flex group-[.toast]:items-center group-[.toast]:justify-center hover:group-[.toast]:bg-primary/90 transition-all shadow-sm shrink-0",
          cancelButton:
            "group-[.toast]:bg-muted group-[.toast]:text-muted-foreground group-[.toast]:rounded-xl group-[.toast]:px-3.5 group-[.toast]:h-9",
          closeButton:
            "group-[.toast]:bg-background group-[.toast]:text-muted-foreground group-[.toast]:border group-[.toast]:border-border/80 hover:group-[.toast]:bg-muted hover:group-[.toast]:text-foreground group-[.toast]:shadow-sm group-[.toast]:transition-colors",
          icon: "group-[.toast]:text-emerald-600 dark:group-[.toast]:text-emerald-400",
          ...toastOptions?.classNames,
        },
      }}
      {...props}
    />
  )
}

export { Toaster, toast }
