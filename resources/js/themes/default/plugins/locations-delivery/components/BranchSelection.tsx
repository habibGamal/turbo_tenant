import React from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { useTranslation } from "react-i18next";
import { Branch } from "@/types";

export interface BranchSelectionProps {
    branches: Branch[];
    value: number;
    onChange: (value: number) => void;
    error?: string;
}

export default function BranchSelection({ branches, value, onChange, error }: BranchSelectionProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";

    return (
        <Card variant="recessed">
            <CardHeader className="p-4 sm:p-6">
                <CardTitle>{t("pleaseSelectBranch")}</CardTitle>
            </CardHeader>
            <CardContent className="px-4 pb-4 pt-0 sm:px-6 sm:pb-6">
                <Select
                    value={value?.toString()}
                    onValueChange={(val) => onChange(parseInt(val))}
                    dir={isRTL ? "rtl" : "ltr"}
                >
                    <SelectTrigger data-testid="branch-select-trigger">
                        <SelectValue placeholder={t("pleaseSelectBranch")} />
                    </SelectTrigger>
                    <SelectContent>
                        {branches.map((branch) => (
                            <SelectItem
                                key={branch.id}
                                value={branch.id.toString()}
                            >
                                <span dir="auto">{branch.name}</span>
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {error && (
                    <p className="text-sm text-destructive mt-2" data-testid="branch-error">
                        {error}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
