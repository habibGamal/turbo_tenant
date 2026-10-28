import React from "react";
import { Link, useNavigate } from "react-router-dom";
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
import { useAuth } from "@/core/context/AuthContext";

interface UserMenuProps {
    user?: {
        name: string;
        email: string;
        avatar?: string;
    } | null;
}

export function UserMenu({ user }: UserMenuProps) {
    const { t } = useTranslation();
    const navigate = useNavigate();
    const { logout } = useAuth();

    if (!user) {
        return (
            <Button
                variant="default"
                size="sm"
                className="gap-2"
                onClick={() => navigate("/login")}
            >
                <User className="h-4 w-4" />
                <span className="hidden sm:inline">{t("login")}</span>
            </Button>
        );
    }

    const handleLogout = async () => {
        try {
            await logout();
            navigate("/");
        } catch (error) {
            console.error("Logout failed:", error);
        }
    };

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
                    <Link to="/profile" className="cursor-pointer">
                        <User className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                        {t("profile")}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link to="/favorites" data-testid="favorites-trigger" className="cursor-pointer">
                        <Heart className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                        {t("myFavorites")}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link to="/orders" data-testid="orders-history-trigger" className="cursor-pointer">
                        <Package className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                        {t("myOrders")}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    onClick={handleLogout}
                    className="w-full cursor-pointer text-destructive focus:text-destructive"
                >
                    <LogOut className="ltr:mr-2 rtl:ml-2 h-4 w-4" />
                    {t("logout")}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
