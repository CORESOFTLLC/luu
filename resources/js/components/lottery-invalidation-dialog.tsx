import { useForm, useHttp } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import { useRef, useState } from 'react';
import {
    preview,
    store,
} from '@/actions/App/Http/Controllers/Admin/LotteryInvalidationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type {
    LotteryInvalidationData,
    LotteryInvalidationPreview,
} from '@/types';

type Props = {
    lotteryId: number;
    invalidation: LotteryInvalidationData;
};

type PreviewResult = {
    range: string;
    data: LotteryInvalidationPreview;
};

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

const formatNumber = (value: number) =>
    new Intl.NumberFormat('mn-MN').format(value);

function isCompleteRange(startsOn: string, endsOn: string, today: string) {
    return (
        DATE_PATTERN.test(startsOn) &&
        DATE_PATTERN.test(endsOn) &&
        startsOn <= endsOn &&
        endsOn <= today
    );
}

export default function LotteryInvalidationDialog({
    lotteryId,
    invalidation,
}: Props) {
    const [open, setOpen] = useState(false);
    const [previewResult, setPreviewResult] = useState<PreviewResult | null>(
        null,
    );
    const previewTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const previewRequest = useHttp<
        Record<string, string>,
        LotteryInvalidationPreview
    >({});
    const form = useForm({
        starts_on: '',
        ends_on: '',
        note: '',
        confirm: '',
        expected_transactions: 0,
        expected_tickets: 0,
    });

    const range = `${form.data.starts_on}|${form.data.ends_on}`;
    const current =
        previewResult && previewResult.range === range
            ? previewResult.data
            : null;
    const rangeComplete = isCompleteRange(
        form.data.starts_on,
        form.data.ends_on,
        invalidation.today,
    );
    const canSubmit =
        invalidation.can_manage &&
        current !== null &&
        !current.already_closed &&
        form.data.note.trim().length >= 5 &&
        form.data.confirm.trim() === 'батлах' &&
        !form.processing;

    function changeRange(startsOn: string, endsOn: string) {
        form.setData((data) => ({
            ...data,
            starts_on: startsOn,
            ends_on: endsOn,
        }));
        form.clearErrors('starts_on', 'ends_on', 'confirm');
        clearTimeout(previewTimer.current);
        previewRequest.cancel();
        previewRequest.clearErrors();

        if (!isCompleteRange(startsOn, endsOn, invalidation.today)) {
            return;
        }

        previewTimer.current = setTimeout(() => {
            previewRequest
                .get(
                    preview.url(lotteryId, {
                        query: { starts_on: startsOn, ends_on: endsOn },
                    }),
                )
                .then((data) =>
                    setPreviewResult({ range: `${startsOn}|${endsOn}`, data }),
                )
                .catch(() => undefined);
        }, 300);
    }

    function openDialog() {
        const { today, valid_from, valid_until } = invalidation;

        form.reset();
        form.clearErrors();
        setOpen(true);
        changeRange(
            valid_from ?? '',
            valid_until ? (valid_until < today ? valid_until : today) : '',
        );
    }

    function closeDialog() {
        clearTimeout(previewTimer.current);
        previewRequest.cancel();
        setOpen(false);
    }

    function submit() {
        if (!canSubmit || current === null) {
            return;
        }

        form.transform((data) => ({
            ...data,
            confirm: data.confirm.trim(),
            expected_transactions: current.transactions,
            expected_tickets: current.tickets,
        }));
        form.post(store.url(lotteryId), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    }

    return (
        <>
            <Button
                variant="outline"
                size="sm"
                disabled={!invalidation.can_manage}
                title={
                    invalidation.can_manage
                        ? undefined
                        : 'Энэ үйлдлийг зөвхөн тусгай эрхтэй админ хийнэ.'
                }
                onClick={openDialog}
            >
                <Ban className="mr-2 h-4 w-4" />
                Бүгдийг хүчингүй болгох
            </Button>

            <Dialog
                open={open}
                onOpenChange={(value) =>
                    value ? setOpen(true) : closeDialog()
                }
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Бүгдийг хүчингүй болгох</DialogTitle>
                        <DialogDescription>
                            Сонгосон хугацааны энэ сугалааны бүх идэвхтэй
                            гүйлгээг хүчингүй болгож, тэдгээрээс үүссэн сугалааг
                            устгана. Огноог Улаанбаатарын цагаар, эхлэх болон
                            дуусах өдрийг оролцуулан тооцно.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="invalidation-starts-on">
                                Эхлэх огноо
                            </Label>
                            <Input
                                id="invalidation-starts-on"
                                type="date"
                                max={invalidation.today}
                                value={form.data.starts_on}
                                onChange={(e) =>
                                    changeRange(
                                        e.target.value,
                                        form.data.ends_on,
                                    )
                                }
                            />
                            <InputError
                                message={
                                    form.errors.starts_on ??
                                    previewRequest.errors.starts_on
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="invalidation-ends-on">
                                Дуусах огноо
                            </Label>
                            <Input
                                id="invalidation-ends-on"
                                type="date"
                                max={invalidation.today}
                                value={form.data.ends_on}
                                onChange={(e) =>
                                    changeRange(
                                        form.data.starts_on,
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={
                                    form.errors.ends_on ??
                                    previewRequest.errors.ends_on
                                }
                            />
                        </div>
                    </div>

                    <div className="rounded-md border p-4 text-sm">
                        {!rangeComplete ? (
                            <p className="text-muted-foreground">
                                Өнөөдрийг ({invalidation.today}) хүртэлх эхлэх,
                                дуусах огноог сонгоход хамаарах гүйлгээний тоо
                                энд гарна.
                            </p>
                        ) : current === null ? (
                            <div className="space-y-2">
                                <Skeleton className="h-4 w-2/3" />
                                <Skeleton className="h-4 w-1/2" />
                                <Skeleton className="h-4 w-3/5" />
                            </div>
                        ) : current.already_closed ? (
                            <p className="text-red-600 dark:text-red-400">
                                Энэ хугацаа бүхэлдээ хүчингүй болсон байна.
                            </p>
                        ) : (
                            <dl className="grid grid-cols-[1fr_auto] gap-x-6 gap-y-1">
                                <dt>Хүчингүй болох гүйлгээ</dt>
                                <dd className="text-right font-semibold">
                                    {formatNumber(current.transactions)}
                                </dd>
                                <dt>Устах сугалаа</dt>
                                <dd className="text-right font-semibold">
                                    {formatNumber(current.tickets)}
                                </dd>
                                <dt>Гүйлгээний нийт дүн</dt>
                                <dd className="text-right font-semibold">
                                    {formatNumber(current.amount)}₮
                                </dd>
                                {current.already_excluded > 0 && (
                                    <>
                                        <dt className="text-muted-foreground">
                                            Өмнө нь цуцлагдсан гүйлгээ
                                        </dt>
                                        <dd className="text-right text-muted-foreground">
                                            {formatNumber(
                                                current.already_excluded,
                                            )}
                                        </dd>
                                    </>
                                )}
                            </dl>
                        )}
                        {current !== null &&
                            current.overlapping_periods.length > 0 && (
                                <p className="mt-3 text-muted-foreground">
                                    Өмнө хаагдсан хугацаатай давхцаж байна:{' '}
                                    {current.overlapping_periods.join(', ')}
                                </p>
                            )}
                    </div>

                    <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        <li>
                            Хайлт, шүүлтүүр, хуудаслалтаас үл хамааран энэ
                            хугацааны бүх гүйлгээнд хамаарна.
                        </li>
                        <li>
                            Гүйлгээний бүртгэл устахгүй, «Цуцлагдсан» төлөвтэй
                            хадгалагдана.
                        </li>
                        <li>
                            Энэ хугацааны гүйлгээг дахин импортлох, мөрийг
                            устгаад дахин оруулах, хуучин хуулгыг хожим оруулах
                            үед ч сугалаа үүсэхгүй.
                        </li>
                        <li>
                            Хүчингүй болсон гүйлгээг дахин сэргээх боломжгүй.
                        </li>
                    </ul>

                    <div className="grid gap-2">
                        <Label htmlFor="invalidation-note">Тайлбар</Label>
                        <Textarea
                            id="invalidation-note"
                            value={form.data.note}
                            maxLength={1000}
                            placeholder="Жишээ нь: 1-р тохирол явагдсан тул өмнөх хугацааг хаав."
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                        />
                        <InputError message={form.errors.note} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="invalidation-confirm">
                            Баталгаажуулахын тулд <b>батлах</b> гэж бичнэ үү
                        </Label>
                        <Input
                            id="invalidation-confirm"
                            value={form.data.confirm}
                            autoComplete="off"
                            onChange={(e) =>
                                form.setData('confirm', e.target.value)
                            }
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    submit();
                                }
                            }}
                        />
                        <InputError
                            message={
                                form.errors.confirm ??
                                form.errors.expected_transactions ??
                                form.errors.expected_tickets
                            }
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeDialog}
                        >
                            Болих
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={!canSubmit}
                            onClick={submit}
                        >
                            {form.processing && <Spinner />}
                            Хүчингүй болгох
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
