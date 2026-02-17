import React from "react";
import { Link, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { User, Heart, Package, LogOut } from "lucide-react";
import { useTranslation } from "react-i18next";

interface UserMenuProps {
    user?: {
        name: string;
        email: string;
        avatar?: string;
    };
}

export function UserMenu({ user }: UserMenuProps) {
    const { t } = useTranslation();

    if (!user) {
        return (
            <Button
                variant="default"
                size="sm"
                className="gap-2"
                onClick={() => router.get("/login")}
            >
                <User className="h-4 w-4" />
                <span className="hidden sm:inline">{t("login")}</span>
            </Button>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="rounded-full">
                    <Avatar className="h-8 w-8">
                        <AvatarImage src={user.avatar} />
                        <AvatarFallback className="bg-primary text-primary-foreground">
                            {user.name.charAt(0).toUpperCase()}
                        </AvatarFallback>
                    </Avatar>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <div className="flex items-center gap-2 p-2">
                    <Avatar className="h-8 w-8">
                        <AvatarImage src={user.avatar} />
                        <AvatarFallback className="bg-primary text-primary-foreground">
                            {user.name.charAt(0).toUpperCase()}
                        </AvatarFallback>
                    </Avatar>
                    <div className="flex flex-col">
                        <p className="text-sm font-medium">{user.name}</p>
                        <p className="text-xs text-muted-foreground">
                            {user.email}
                        </p>
                    </div>
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={route("profile.edit")} className="cursor-pointer">
                        <User className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                        {t("profile")}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={route("favorites.index")} className="cursor-pointer">
                        <Heart className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                        {t("myFavorites")}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href="/orders" className="cursor-pointer">
                        <Package className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                        {t("myOrders")}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="w-full cursor-pointer"
                    >
                        <LogOut className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                        {t("logout")}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
