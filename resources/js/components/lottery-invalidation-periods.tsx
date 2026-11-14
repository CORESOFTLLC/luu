import { useForm } from '@inertiajs/react';
import { CalendarRange, History } from 'lucide-react';
import type { FormEvent } from 'react';
import { update } from '@/actions/App/Http/Controllers/Admin/LotteryValidPeriodController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { LotteryInvalidationData } from '@/types';

type Props = {
    lotteryId: number;
    invalidation: LotteryInvalidationData;
};

const formatNumber = (value: number) =>
    new Intl.NumberFormat('mn-MN').format(value);

export default function LotteryInvalidationPeriods({
    lotteryId,
    invalidation,
}: Props) {
    const form = useForm({
        valid_from: invalidation.valid_from ?? '',
        valid_until: invalidation.valid_until ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            valid_from: data.valid_from || null,
            valid_until: data.valid_until || null,
        }));
        form.put(update.url(lotteryId), { preserveScroll: true });
    }

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <CalendarRange className="h-5 w-5" />
                        Хүчинтэй хугацаа
                    </CardTitle>
                    <CardDescription>
                        Одоогийн тохиролд оролцох гүйлгээний огноо
                        (Улаанбаатарын цагаар, эхлэх болон дуусах өдрийг
                        оролцуулан). Тохирол явагдсаны дараа энэ хугацааг
                        «Бүгдийг хүчингүй болгох»-оор хааж, дараагийн тохирлын
                        хугацааг энд шинээр тохируулна.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4 text-sm">
                    <p>
                        Одоогийн хүчинтэй хугацаа:{' '}
                        <span className="font-semibold">
                            {invalidation.valid_from && invalidation.valid_until
                                ? `${invalidation.valid_from} – ${invalidation.valid_until}`
                                : 'Тохируулаагүй'}
                        </span>
                    </p>

                    <form
                        onSubmit={submit}
                        className="flex flex-wrap items-end gap-3"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="valid-from">Эхлэх огноо</Label>
                            <Input
                                id="valid-from"
                                type="date"
                                value={form.data.valid_from}
                                disabled={!invalidation.can_manage}
                                onChange={(e) =>
                                    form.setData('valid_from', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="valid-until">Дуусах огноо</Label>
                            <Input
                                id="valid-until"
                                type="date"
                                value={form.data.valid_until}
                                disabled={!invalidation.can_manage}
                                onChange={(e) =>
                                    form.setData('valid_until', e.target.value)
                                }
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={
                                !invalidation.can_manage || form.processing
                            }
                        >
                            {form.processing && <Spinner />}
                            Хадгалах
                        </Button>
                    </form>
                    <InputError message={form.errors.valid_from} />
                    <InputError message={form.errors.valid_until} />

                    {invalidation.valid_period_overlaps_closed && (
                        <p className="text-red-600 dark:text-red-400">
                            Хүчинтэй хугацаа хүчингүй болгосон хугацаатай
                            давхцаж байна. Шинэ хугацааг тохируулна уу.
                        </p>
                    )}
                    {(invalidation.active_outside_valid_period ?? 0) > 0 && (
                        <p className="text-amber-700 dark:text-amber-400">
                            Хүчинтэй хугацаанаас гадуур{' '}
                            {formatNumber(
                                invalidation.active_outside_valid_period ?? 0,
                            )}{' '}
                            идэвхтэй гүйлгээ байна.
                        </p>
                    )}
                    <p className="text-muted-foreground">
                        Энэ огноо нь админд зориулсан мэдээлэл бөгөөд импортыг
                        өөрөө хаахгүй. Сугалаа үүсэхийг зөвхөн хүчингүй болгосон
                        хугацаа хориглоно.
                    </p>
                    {!invalidation.can_manage && (
                        <p className="text-muted-foreground">
                            Хүчинтэй хугацааг өөрчлөх, гүйлгээг бөөнөөр хүчингүй
                            болгох эрх танд алга.
                        </p>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <History className="h-5 w-5" />
                        Хүчингүй болгосон түүх
                    </CardTitle>
                    <CardDescription>
                        Эдгээр хугацааны гүйлгээнээс дахин импортлоход ч сугалаа
                        үүсэхгүй, гараар сэргээх боломжгүй.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {invalidation.periods.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Одоогоор хүчингүй болгосон хугацаа алга.
                        </p>
                    ) : (
                        <ul className="max-h-80 space-y-3 overflow-y-auto">
                            {invalidation.periods.map((period) => (
                                <li
                                    key={period.id}
                                    className="rounded-md border p-3 text-sm"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-semibold">
                                            {period.starts_on} –{' '}
                                            {period.ends_on}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {period.created_at} ·{' '}
                                            {period.user_name ?? 'Тодорхойгүй'}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-muted-foreground">
                                        {formatNumber(
                                            period.transactions_count,
                                        )}{' '}
                                        гүйлгээ ·{' '}
                                        {formatNumber(period.tickets_count)}{' '}
                                        сугалаа ·{' '}
                                        {formatNumber(period.amount_total)}₮
                                    </p>
                                    <p className="mt-2 break-words whitespace-pre-line">
                                        {period.note}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
