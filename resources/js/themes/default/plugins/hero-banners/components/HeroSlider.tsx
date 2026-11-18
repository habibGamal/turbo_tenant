import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
    type CarouselApi,
} from "@/components/ui/carousel";
import { Sparkles, Timer, TrendingUp } from "lucide-react";
import Autoplay from "embla-carousel-autoplay";
import { useTranslation } from "react-i18next";
import { ImageWithFallback } from "@/components/ui/image";

interface HeroSlide {
    id: number;
    title: string;
    title_ar?: string;
    subtitle: string;
    subtitle_ar?: string;
    badge?: string;
    badge_ar?: string;
    cta_text: string;
    cta_text_ar?: string;
    cta_link: string;
    secondary_cta_text?: string;
    secondary_cta_text_ar?: string;
    secondary_cta_link?: string;
    image?: string;
    gradient?: string;
    icon?: string;
}

interface HeroSliderProps {
    slides?: HeroSlide[];
}

function resolveStorefrontHref(link?: string): { href: string; isExternal: boolean } {
    const raw = (link || "").trim();
    if (!raw) return { href: "/", isExternal: false };
    if (raw.startsWith("/")) return { href: raw, isExternal: false };
    try {
        const url = new URL(raw);
        const sameTenantHost =
            url.hostname.endsWith(".turbosoftspace.com") ||
            url.hostname.endsWith(".localhost") ||
            (typeof window !== "undefined" && url.hostname === window.location.hostname);
        if (sameTenantHost) {
            return { href: `${url.pathname}${url.search}${url.hash}` || "/", isExternal: false };
        }
        return { href: raw, isExternal: true };
    } catch {
        return { href: raw, isExternal: false };
    }
}

export default function HeroSlider({ slides = [] }: HeroSliderProps) {
    const { t, i18n } = useTranslation();
    const [api, setApi] = useState<CarouselApi>();
    const [current, setCurrent] = useState(0);

    const prefersReducedMotion =
        typeof window !== "undefined" &&
        typeof window.matchMedia === "function" &&
        window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    const plugin = React.useRef(
        Autoplay({
            delay: 5000,
            stopOnInteraction: true,
            active: !prefersReducedMotion,
        })
    );

    useEffect(() => {
        if (!api) return;

        const onSelect = () => {
            setCurrent(api.selectedScrollSnap());
        };

        setCurrent(api.selectedScrollSnap());
        api.on("select", onSelect);

        return () => {
            api.off("select", onSelect);
        };
    }, [api]);

    if (!slides || slides.length === 0) {
        return null;
    }

    const slidesToUse = slides;

    const getIcon = (iconName: string) => {
        const iconClass = "h-16 w-16 md:h-24 md:w-24";
        switch (iconName) {
            case "sparkles":
                return <Sparkles className={iconClass} />;
            case "timer":
                return <Timer className={iconClass} />;
            case "trending":
                return <TrendingUp className={iconClass} />;
            default:
                return <Sparkles className={iconClass} />;
        }
    };

    const getText = (text?: string, textAr?: string) => {
        return i18n.language === "ar" && textAr ? textAr : (text || "");
    };

    return (
        <section data-testid="hero-banners-slider" className="relative overflow-hidden">
            <Carousel
                dir={i18n.dir()}
                opts={{
                    align: "start",
                    loop: true,
                    direction: i18n.dir(),
                }}
                plugins={prefersReducedMotion ? [] : [plugin.current]}
                setApi={setApi}
                className="w-full"
                onMouseEnter={plugin.current.stop}
                onMouseLeave={plugin.current.reset}
                onFocusCapture={plugin.current.stop}
                onBlurCapture={plugin.current.reset}
            >
                <CarouselContent>
                    {slidesToUse.map((slide, index) => {
                        const badgeText = getText(slide.badge, slide.badge_ar).trim();
                        const titleText = getText(slide.title, slide.title_ar).trim();
                        const subtitleText = getText(slide.subtitle, slide.subtitle_ar).trim();
                        const ctaText = getText(slide.cta_text, slide.cta_text_ar).trim();
                        const secondaryCtaText = getText(
                            slide.secondary_cta_text,
                            slide.secondary_cta_text_ar
                        ).trim();

                        const hasPrimaryCta = Boolean(slide.cta_link && ctaText);
                        const hasSecondaryCta = Boolean(slide.secondary_cta_link && secondaryCtaText);
                        const hasContent = Boolean(
                            badgeText || titleText || subtitleText || hasPrimaryCta || hasSecondaryCta
                        );

                        const isClickableSlide = Boolean(slide.cta_link && !hasPrimaryCta && !hasSecondaryCta);

                        const primaryCta = resolveStorefrontHref(slide.cta_link);
                        const secondaryCta = resolveStorefrontHref(slide.secondary_cta_link);

                        const slideContent = (
                            <div className="relative min-h-[300px] sm:min-h-[360px] md:aspect-[16/9] md:max-h-[580px] w-full flex items-center overflow-hidden">
                                {/* Background: Image or Gradient */}
                                {slide.image ? (
                                    <>
                                        <div className="absolute inset-0 z-0">
                                            <ImageWithFallback
                                                src={slide.image}
                                                alt={titleText || t("heroSlide", "Hero Slide")}
                                                className="w-full h-full object-cover"
                                                priority={index === 0}
                                                loading={index === 0 ? "eager" : "lazy"}
                                                fetchPriority={index === 0 ? "high" : "low"}
                                            />
                                            {hasContent && <div className="absolute inset-0 bg-black/45" />}
                                        </div>
                                        {hasContent && (
                                            <div
                                                className={`absolute inset-0 bg-gradient-to-br ${slide.gradient} opacity-60 mix-blend-overlay`}
                                            />
                                        )}
                                    </>
                                ) : (
                                    <div
                                        className={`absolute inset-0 bg-gradient-to-br ${slide.gradient}`}
                                    />
                                )}

                                {/* Decorative Background Elements - Only if no image */}
                                {!slide.image && (
                                    <>
                                        <div className="absolute top-0 start-0 w-72 h-72 bg-primary/10 rounded-full blur-3xl ltr:-translate-x-1/2 rtl:translate-x-1/2 -translate-y-1/2" />
                                        <div className="absolute bottom-0 end-0 w-96 h-96 bg-secondary/10 rounded-full blur-3xl ltr:translate-x-1/2 rtl:-translate-x-1/2 translate-y-1/2" />
                                    </>
                                )}

                                {/* Content Block anchored to bottom-start over a readable scrim */}
                                {hasContent && (
                                    <div className="absolute inset-0 z-10 bg-gradient-to-t from-black/75 via-black/35 to-transparent flex flex-col justify-end items-start px-5 pt-8 pb-14 sm:p-10 lg:p-14 sm:pb-16 text-start">
                                        <div className="max-w-xl text-white space-y-2.5 sm:space-y-4">
                                            {badgeText && (
                                                <div>
                                                    <Badge variant="secondary">
                                                        {badgeText}
                                                    </Badge>
                                                </div>
                                            )}
                                            {titleText && (
                                                <h2 className="text-2xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-white">
                                                    {titleText}
                                                </h2>
                                            )}
                                            {subtitleText && (
                                                <p className="text-sm sm:text-base lg:text-lg text-white/90">
                                                    {subtitleText}
                                                </p>
                                            )}
                                            {(hasPrimaryCta || hasSecondaryCta) && (
                                                <div className="flex flex-wrap items-center gap-3 pt-1 sm:pt-2">
                                                    {hasPrimaryCta && (
                                                        <Button
                                                            asChild
                                                            size="lg"
                                                            data-testid="hero-slide-cta"
                                                            className="bg-white text-primary hover:bg-white/95 shadow-lg font-semibold border border-white/30"
                                                        >
                                                            {primaryCta.isExternal ? (
                                                                <a href={primaryCta.href}>{ctaText}</a>
                                                            ) : (
                                                                <Link to={primaryCta.href}>{ctaText}</Link>
                                                            )}
                                                        </Button>
                                                    )}
                                                    {hasSecondaryCta && (
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="lg"
                                                            className="border-white/40 text-white hover:bg-white/15 hover:text-white bg-black/25 backdrop-blur-sm"
                                                        >
                                                            {secondaryCta.isExternal ? (
                                                                <a href={secondaryCta.href}>{secondaryCtaText}</a>
                                                            ) : (
                                                                <Link to={secondaryCta.href}>
                                                                    {secondaryCtaText}
                                                                </Link>
                                                            )}
                                                        </Button>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        );

                        return (
                            <CarouselItem data-testid="hero-slide" key={slide.id}>
                                {isClickableSlide ? (
                                    primaryCta.isExternal ? (
                                        <a href={primaryCta.href} className="block w-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">
                                            {slideContent}
                                        </a>
                                    ) : (
                                        <Link to={primaryCta.href} className="block w-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">
                                            {slideContent}
                                        </Link>
                                    )
                                ) : (
                                    slideContent
                                )}
                            </CarouselItem>
                        );
                    })}
                </CarouselContent>

                {slidesToUse.length > 1 && (
                    <>
                        <CarouselPrevious
                            data-testid="hero-prev-button"
                            aria-label={t("heroPrevSlide", "Previous slide")}
                            className="hidden md:flex start-4 h-11 w-11 bg-black/40 hover:bg-black/65 text-white border-white/25 backdrop-blur-sm z-20 shadow-lg disabled:opacity-35"
                        />
                        <CarouselNext
                            data-testid="hero-next-button"
                            aria-label={t("heroNextSlide", "Next slide")}
                            className="hidden md:flex end-4 h-11 w-11 bg-black/40 hover:bg-black/65 text-white border-white/25 backdrop-blur-sm z-20 shadow-lg disabled:opacity-35"
                        />
                    </>
                )}
            </Carousel>

            {/* Dots Indicator */}
            <div className="absolute bottom-1 inset-x-0 flex justify-center items-center gap-2 z-20">
                {slidesToUse.map((_, index) => (
                    <button
                        key={index}
                        type="button"
                        onClick={() => api?.scrollTo(index)}
                        className="group p-3 min-w-[44px] min-h-[44px] flex items-center justify-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white rounded-full"
                        aria-label={t("heroGoToSlide", "Go to slide {{index}}", {
                            index: index + 1,
                        })}
                        aria-current={index === current ? "true" : undefined}
                    >
                        <span
                            className={`h-2 rounded-full transition-all shadow-sm ${
                                index === current
                                    ? "w-8 bg-white"
                                    : "w-2.5 bg-white/50 group-hover:bg-white/80"
                            }`}
                        />
                    </button>
                ))}
            </div>
        </section>
    );
}
