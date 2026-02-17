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

interface BranchSelectionProps {
    branches: Branch[];
    value: number;
    onChange: (value: number) => void;
}

export default function BranchSelection({ branches, value, onChange }: BranchSelectionProps) {
    const { t, i18n } = useTranslation();
    const isRTL = i18n.language === "ar";

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t("pleaseSelectBranch")}</CardTitle>
            </CardHeader>
            <CardContent>
                <Select
                    value={value?.toString()}
                    onValueChange={(val) => onChange(parseInt(val))}
                    dir={isRTL ? "rtl" : "ltr"}
                >
                    <SelectTrigger>
                        <SelectValue placeholder={t("pleaseSelectBranch")} />
                    </SelectTrigger>
                    <SelectContent>
                        {branches.map((branch) => (
                            <SelectItem
                                key={branch.id}
                                value={branch.id.toString()}
                            >
                                {branch.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </CardContent>
        </Card>
    );
}
