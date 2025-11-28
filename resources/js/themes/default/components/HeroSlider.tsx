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
    gradient: string;
    icon: string;
}

interface HeroSliderProps {
    slides?: HeroSlide[];
}

export default function HeroSlider({ slides = [] }: HeroSliderProps) {
    const { t, i18n } = useTranslation();
    const [api, setApi] = useState<CarouselApi>();
    const [current, setCurrent] = useState(0);

    if (!slides || slides.length === 0) {
        return null;
    }

    const slidesToUse = slides;

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
                                className={`relative aspect-[16/9] flex items-center overflow-hidden`}
                            >
                                {/* Background: Image or Gradient */}
                                {slide.image ? (
                                    <>
                                        <div className="absolute inset-0 z-0">
                                            <img
                                                src={`/storage/${slide.image}`}
                                                alt={slide.title}
                                                className="w-full h-full object-cover"
                                            />
                                            <div className="absolute inset-0 bg-black/40" />
                                        </div>
                                        <div className={`absolute inset-0 bg-gradient-to-br ${slide.gradient} opacity-60 mix-blend-overlay`} />
                                    </>
                                ) : (
                                    <div className={`absolute inset-0 bg-gradient-to-br ${slide.gradient}`} />
                                )}


                                {/* Decorative Background Elements - Only if no image */}
                                {!slide.image && (
                                    <>
                                        <div className="absolute top-0 ltr:left-0 rtl:right-0 w-72 h-72 bg-primary/10 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2" />
                                        <div className="absolute bottom-0 ltr:right-0 rtl:left-0 w-96 h-96 bg-secondary/10 rounded-full blur-3xl translate-x-1/2 translate-y-1/2" />
                                    </>
                                )}
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
                        className={`h-2 rounded-full transition-all ${index === current
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
