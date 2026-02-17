import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { ShoppingBag } from "lucide-react";
import { router } from "@inertiajs/react";
import { useTranslation } from "react-i18next";

export default function EmptyCart() {
    const { t } = useTranslation();

    return (
        <Card className="py-16">
            <CardContent className="flex flex-col items-center gap-6">
                <div className="w-24 h-24 rounded-full bg-primary/10 flex items-center justify-center">
                    <ShoppingBag className="w-12 h-12 text-primary" />
                </div>
                <div className="text-center space-y-2">
                    <h2 className="text-2xl font-semibold">
                        {t("cartEmpty")}
                    </h2>
                    <p className="text-muted-foreground">
                        {t("cartEmptyDescription")}
                    </p>
                </div>
                <Button
                    onClick={() => router.visit(route("home"))}
                    size="lg"
                >
                    {t("startShopping")}
                </Button>
            </CardContent>
        </Card>
    );
}
