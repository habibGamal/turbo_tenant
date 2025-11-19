import React, { useEffect, useState } from "react";
import { Link } from "@inertiajs/react";
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
import { ArrowRight, Sparkles, Timer, TrendingUp } from "lucide-react";
import Autoplay from "embla-carousel-autoplay";
import { useTranslation } from "react-i18next";

interface HeroSlide {
    id: number;
    title: string;
    titleAr?: string;
    subtitle: string;
    subtitleAr?: string;
    badge?: string;
    badgeAr?: string;
    ctaText: string;
    ctaTextAr?: string;
    ctaLink: string;
    secondaryCtaText?: string;
    secondaryCtaTextAr?: string;
    secondaryCtaLink?: string;
    image?: string;
    gradient: string;
    icon: "sparkles" | "timer" | "trending";
}

interface HeroSliderProps {
    slides?: HeroSlide[];
}

export default function HeroSlider({ slides }: HeroSliderProps) {
    const { t, i18n } = useTranslation();
    const [api, setApi] = useState<CarouselApi>();
    const [current, setCurrent] = useState(0);

    const defaultSlides: HeroSlide[] = [
        {
            id: 1,
            title: t("heroSlide1Title"),
            titleAr: t("heroSlide1Title"),
            subtitle: t("heroSlide1Subtitle"),
            subtitleAr: t("heroSlide1Subtitle"),
            badge: t("heroSlide1Badge"),
            badgeAr: t("heroSlide1Badge"),
            ctaText: t("heroSlide1Cta"),
            ctaTextAr: t("heroSlide1Cta"),
            ctaLink: "/menu",
            secondaryCtaText: t("heroSlide1SecondaryCta"),
            secondaryCtaTextAr: t("heroSlide1SecondaryCta"),
            secondaryCtaLink: "/menu",
            gradient: "from-orange-500/20 via-red-500/10 to-pink-500/20",
            icon: "sparkles",
        },
        {
            id: 2,
            title: t("heroSlide2Title"),
            titleAr: t("heroSlide2Title"),
            subtitle: t("heroSlide2Subtitle"),
            subtitleAr: t("heroSlide2Subtitle"),
            badge: t("heroSlide2Badge"),
            badgeAr: t("heroSlide2Badge"),
            ctaText: t("heroSlide2Cta"),
            ctaTextAr: t("heroSlide2Cta"),
            ctaLink: "/menu",
            secondaryCtaText: t("heroSlide2SecondaryCta"),
            secondaryCtaTextAr: t("heroSlide2SecondaryCta"),
            secondaryCtaLink: "/orders",
            gradient: "from-blue-500/20 via-cyan-500/10 to-teal-500/20",
            icon: "timer",
        },
        {
            id: 3,
            title: t("heroSlide3Title"),
            titleAr: t("heroSlide3Title"),
            subtitle: t("heroSlide3Subtitle"),
            subtitleAr: t("heroSlide3Subtitle"),
            badge: t("heroSlide3Badge"),
            badgeAr: t("heroSlide3Badge"),
            ctaText: t("heroSlide3Cta"),
            ctaTextAr: t("heroSlide3Cta"),
            ctaLink: "/menu",
            secondaryCtaText: t("heroSlide3SecondaryCta"),
            secondaryCtaTextAr: t("heroSlide3SecondaryCta"),
            secondaryCtaLink: "/about",
            gradient: "from-purple-500/20 via-violet-500/10 to-fuchsia-500/20",
            icon: "trending",
        },
    ];

    const slidesToUse = slides || defaultSlides;

    const plugin = React.useRef(
        Autoplay({ delay: 5000, stopOnInteraction: true })
    );

    useEffect(() => {
        if (!api) return;

        setCurrent(api.selectedScrollSnap());

        api.on("select", () => {
            setCurrent(api.selectedScrollSnap());
        });
    }, [api]);

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

    const getText = (text: string, textAr?: string) => {
        return i18n.language === "ar" && textAr ? textAr : text;
    };

    return (
        <section className="relative overflow-hidden">
            <Carousel
                dir="ltr"
                opts={{
                    align: "start",
                    loop: true,
                }}
                plugins={[plugin.current]}
                setApi={setApi}
                className="w-full"
                onMouseEnter={plugin.current.stop}
                onMouseLeave={plugin.current.reset}
            >
                <CarouselContent>
                    {slidesToUse.map((slide, index) => (
                        <CarouselItem key={slide.id}>
                            <div
                                className={`relative bg-gradient-to-br ${slide.gradient} min-h-[500px] md:min-h-[600px] flex items-center`}
                            >
                                <div className="container mx-auto px-4 py-12 md:py-20">
                                    <div className="grid gap-8 md:grid-cols-2 items-center">
                                        {/* Content */}
                                        <div className="space-y-6 text-center md:text-start rtl:md:text-end">
                                            {slide.badge && (
                                                <Badge
                                                    variant="secondary"
                                                    className="w-fit mx-auto md:mx-0 rtl:md:mx-0 text-sm px-4 py-1.5 animate-in fade-in slide-in-from-top-4 duration-700"
                                                    style={{
                                                        animationDelay: `${
                                                            index * 100
                                                        }ms`,
                                                    }}
                                                >
                                                    <Sparkles className="h-3 w-3 ltr:mr-1 rtl:ml-1" />
                                                    {getText(
                                                        slide.badge,
                                                        slide.badgeAr
                                                    )}
                                                </Badge>
                                            )}

                                            <h1
                                                className="text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight animate-in fade-in slide-in-from-top-6 duration-700"
                                                style={{
                                                    animationDelay: `${
                                                        index * 100 + 100
                                                    }ms`,
                                                }}
                                            >
                                                {getText(
                                                    slide.title,
                                                    slide.titleAr
                                                )}
                                            </h1>

                                            <p
                                                className="text-lg md:text-xl text-muted-foreground max-w-xl mx-auto md:mx-0 rtl:md:mx-0 animate-in fade-in slide-in-from-top-8 duration-700"
                                                style={{
                                                    animationDelay: `${
                                                        index * 100 + 200
                                                    }ms`,
                                                }}
                                            >
                                                {getText(
                                                    slide.subtitle,
                                                    slide.subtitleAr
                                                )}
                                            </p>

                                            <div
                                                className="flex flex-col sm:flex-row gap-4 justify-center md:justify-start rtl:md:justify-start animate-in fade-in slide-in-from-top-10 duration-700"
                                                style={{
                                                    animationDelay: `${
                                                        index * 100 + 300
                                                    }ms`,
                                                }}
                                            >
                                                <Link href={slide.ctaLink}>
                                                    <Button
                                                        size="lg"
                                                        className="w-full sm:w-auto gap-2 group shadow-lg hover:shadow-xl transition-all"
                                                    >
                                                        {getText(
                                                            slide.ctaText,
                                                            slide.ctaTextAr
                                                        )}
                                                        <ArrowRight className="h-4 w-4 group-hover:translate-x-1 rtl:group-hover:-translate-x-1 transition-transform" />
                                                    </Button>
                                                </Link>

                                                {slide.secondaryCtaText &&
                                                    slide.secondaryCtaLink && (
                                                        <Link
                                                            href={
                                                                slide.secondaryCtaLink
                                                            }
                                                        >
                                                            <Button
                                                                size="lg"
                                                                variant="outline"
                                                                className="w-full sm:w-auto backdrop-blur-sm bg-background/50 hover:bg-background/80"
                                                            >
                                                                {getText(
                                                                    slide.secondaryCtaText,
                                                                    slide.secondaryCtaTextAr
                                                                )}
                                                            </Button>
                                                        </Link>
                                                    )}
                                            </div>
                                        </div>

                                        {/* Visual Element */}
                                        <div
                                            className="relative aspect-square md:aspect-auto md:h-[400px] animate-in fade-in zoom-in-50 duration-1000"
                                            style={{
                                                animationDelay: `${
                                                    index * 100 + 200
                                                }ms`,
                                            }}
                                        >
                                            <div className="absolute inset-0 rounded-3xl bg-gradient-to-br from-background/40 to-background/10 backdrop-blur-sm" />
                                            <div className="relative flex h-full items-center justify-center">
                                                <div className="text-primary/60 animate-pulse">
                                                    {getIcon(slide.icon)}
                                                </div>
                                                {/* Decorative Elements */}
                                                <div className="absolute top-10 ltr:right-10 rtl:left-10 h-20 w-20 rounded-full bg-primary/20 blur-2xl animate-pulse" />
                                                <div
                                                    className="absolute bottom-10 ltr:left-10 rtl:right-10 h-32 w-32 rounded-full bg-secondary/20 blur-3xl animate-pulse"
                                                    style={{
                                                        animationDelay: "1s",
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Decorative Background Elements */}
                                <div className="absolute top-0 ltr:left-0 rtl:right-0 w-72 h-72 bg-primary/10 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2" />
                                <div className="absolute bottom-0 ltr:right-0 rtl:left-0 w-96 h-96 bg-secondary/10 rounded-full blur-3xl translate-x-1/2 translate-y-1/2" />
                            </div>
                        </CarouselItem>
                    ))}
                </CarouselContent>

                {/* Navigation Arrows - Hidden on Mobile */}
                <div className="hidden md:block">
                    <CarouselPrevious className="ltr:left-4 rtl:right-4" />
                    <CarouselNext className="ltr:right-4 rtl:left-4" />
                </div>
            </Carousel>

            {/* Dots Indicator */}
            <div className="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2 z-10">
                {slidesToUse.map((_, index) => (
                    <button
                        key={index}
                        onClick={() => api?.scrollTo(index)}
                        className={`h-2 rounded-full transition-all ${
                            index === current
                                ? "w-8 bg-primary"
                                : "w-2 bg-primary/30 hover:bg-primary/50"
                        }`}
                        aria-label={`Go to slide ${index + 1}`}
                    />
                ))}
            </div>
        </section>
    );
}
