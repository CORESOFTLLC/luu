import { Form, Head, router } from '@inertiajs/react';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import { Badge } from '@/components/ui/badge';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { LotteryData } from '@/types';

type Props = {
    lottery: LotteryData;
};

function formatDatetime(value: string | null) {
    if (!value) return '';
    return value.replace(' ', 'T').slice(0, 16);
}

export default function AdminLotteryEdit({ lottery }: Props) {
    const [unlimited, setUnlimited] = useState(lottery.total_tickets === null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [confirmText, setConfirmText] = useState('');
    const [deleteError, setDeleteError] = useState<string | undefined>();
    const [deleting, setDeleting] = useState(false);
    const confirmed = confirmText.trim() === 'батлах';

    function submitDelete() {
        if (!confirmed) return;
        setDeleting(true);
        router.delete(`/admin/lotteries/${lottery.id}`, {
            data: { confirm: confirmText.trim() },
            onError: (errors) => setDeleteError(errors.confirm),
            onFinish: () => setDeleting(false),
        });
    }
    return (
        <>
            <Head title={`${lottery.name} засах`} />

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Сугалаа устгах</DialogTitle>
                        <DialogDescription>
                            "{lottery.name}" сугалаа болон түүний бүх гүйлгээ, сугалааны дугаар, банкны импорт, зургууд бүрмөсөн устна. Энэ үйлдлийг буцаах боломжгүй.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="delete-confirm">Баталгаажуулахын тулд <b>батлах</b> гэж бичнэ үү</Label>
                        <Input
                            id="delete-confirm"
                            value={confirmText}
                            autoComplete="off"
                            onChange={(e) => setConfirmText(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') submitDelete();
                            }}
                        />
                        <InputError message={deleteError} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setDeleteOpen(false)}>
                            Болих
                        </Button>
                        <Button type="button" variant="destructive" disabled={!confirmed || deleting} onClick={submitDelete}>
                            {deleting && <Spinner />}
                            Устгах
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Сугалаа засах"
                        description={lottery.name}
                    />
                    <div className="flex items-center gap-3">
                        <Badge variant={lottery.is_active ? 'default' : 'secondary'}>
                            {lottery.is_active ? 'Идэвхтэй' : 'Идэвхгүй'}
                        </Badge>
                        <Button variant="outline" size="sm" asChild>
                            <a href={`/admin/lotteries/${lottery.id}/tickets/print`} target="_blank" rel="noopener">
                                Бүх сугалааг хэвлэх
                            </a>
                        </Button>
                        <Button
                            type="button"
                            variant={lottery.is_active ? 'destructive' : 'default'}
                            size="sm"
                            onClick={() => {
                                router.post(`/admin/lotteries/${lottery.id}/toggle`);
                            }}
                        >
                            {lottery.is_active ? 'Идэвхгүй болгох' : 'Идэвхжүүлэх'}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => {
                                setConfirmText('');
                                setDeleteError(undefined);
                                setDeleteOpen(true);
                            }}
                        >
                            Устгах
                        </Button>
                    </div>
                </div>

                <Form
                    action={`/admin/lotteries/${lottery.id}`}
                    method="put"
                    encType="multipart/form-data"
                    options={{ forceFormData: true }}
                    className="max-w-lg space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Нэр</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={lottery.name}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="image">Зураг</Label>
                                {lottery.image && (
                                    <img
                                        src={`/storage/${lottery.image}`}
                                        alt={lottery.name}
                                        className="h-32 w-auto rounded-md object-cover"
                                    />
                                )}
                                <Input
                                    id="image"
                                    name="image"
                                    type="file"
                                    accept="image/*"
                                />
                                <InputError message={errors.image} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">
                                    Тайлбар / Шагналын мэдээлэл
                                </Label>
                                <RichTextEditor
                                    name="description"
                                    value={lottery.description ?? ''}
                                    placeholder="Шагналын мэдээлэл оруулна уу..."
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_account">
                                    Дансны дугаар
                                </Label>
                                <Input
                                    id="bank_account"
                                    name="bank_account"
                                    defaultValue={lottery.bank_account ?? ''}
                                    placeholder="5022421111"
                                />
                                <InputError message={errors.bank_account} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_name">Банкны нэр</Label>
                                <Input
                                    id="bank_name"
                                    name="bank_name"
                                    defaultValue={lottery.bank_name ?? ''}
                                    placeholder="Хаан банк"
                                />
                                <InputError message={errors.bank_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="location">Байршил</Label>
                                <Input
                                    id="location"
                                    name="location"
                                    defaultValue={lottery.location ?? ''}
                                    placeholder="Дархан Хот"
                                />
                                <InputError message={errors.location} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="price_per_ticket">
                                    Нэгж үнэ (₮)
                                </Label>
                                <Input
                                    id="price_per_ticket"
                                    name="price_per_ticket"
                                    type="number"
                                    required
                                    defaultValue={lottery.price_per_ticket}
                                    min={1000}
                                />
                                <InputError
                                    message={errors.price_per_ticket}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="total_tickets">
                                    Нийт сугалааны тоо
                                </Label>
                                <Input
                                    id="total_tickets"
                                    name="total_tickets"
                                    type="number"
                                    required={!unlimited}
                                    disabled={unlimited}
                                    defaultValue={lottery.total_tickets ?? ''}
                                    min={1}
                                />
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="is_unlimited"
                                        value="1"
                                        checked={unlimited}
                                        onChange={(e) =>
                                            setUnlimited(e.target.checked)
                                        }
                                        className="size-4 rounded border-input"
                                    />
                                    Хязгааргүй (нийт тоо тогтоохгүй)
                                </label>
                                <InputError message={errors.total_tickets} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="max_tickets_per_transaction">
                                    Нэг гүйлгээнд олгох дээд сугалаа
                                </Label>
                                <Input
                                    id="max_tickets_per_transaction"
                                    name="max_tickets_per_transaction"
                                    type="number"
                                    min={1}
                                    defaultValue={lottery.max_tickets_per_transaction ?? ''}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Хоосон бол хязгааргүй. Илүү шилжүүлсэн дүнг "Буцаах дүн" болгож тэмдэглэнэ.
                                </p>
                                <InputError message={errors.max_tickets_per_transaction} />
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="show_ticket_numbers"
                                    value="1"
                                    defaultChecked={!!lottery.show_ticket_numbers}
                                    className="size-4 rounded border-input"
                                />
                                Сугалааны дугаар харуулах (унтраалттай бол утасны дугаараар жагсаана)
                            </label>

                            <div className="grid gap-2">
                                <Label htmlFor="starts_at">Эхлэх огноо</Label>
                                <Input
                                    id="starts_at"
                                    name="starts_at"
                                    type="datetime-local"
                                    required
                                    defaultValue={formatDatetime(
                                        lottery.starts_at,
                                    )}
                                />
                                <InputError message={errors.starts_at} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="draws_at">
                                    Азтанг тодруулах огноо
                                </Label>
                                <Input
                                    id="draws_at"
                                    name="draws_at"
                                    type="datetime-local"
                                    required
                                    defaultValue={formatDatetime(
                                        lottery.draws_at,
                                    )}
                                />
                                <InputError message={errors.draws_at} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                            >
                                {processing && <Spinner />}
                                Хадгалах
                            </Button>
                        </>
                    )}
                </Form>

                {/* Gallery */}
                <div className="max-w-lg space-y-4">
                    <Label>Нэмэлт зургууд</Label>

                    {lottery.images && lottery.images.length > 0 && (
                        <div className="grid grid-cols-3 gap-3">
                            {lottery.images.map((img) => (
                                <div
                                    key={img.id}
                                    className="group relative overflow-hidden rounded-md border"
                                >
                                    <img
                                        src={`/storage/${img.path}`}
                                        alt=""
                                        className="aspect-square w-full object-cover"
                                    />
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        className="absolute right-1 top-1 h-7 w-7 p-0 opacity-0 transition-opacity group-hover:opacity-100"
                                        onClick={() => {
                                            router.delete(
                                                `/admin/lotteries/images/${img.id}`,
                                            );
                                        }}
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}

                    <GalleryUpload lotteryId={lottery.id} />
                </div>
            </div>
        </>
    );
}

function GalleryUpload({ lotteryId }: { lotteryId: number }) {
    const fileInputRef = useRef<HTMLInputElement>(null);

    return (
        <Form
            action={`/admin/lotteries/${lotteryId}/images`}
            method="post"
            encType="multipart/form-data"
            options={{ forceFormData: true }}
        >
            {({ processing, errors }) => (
                <div className="flex items-center gap-3">
                    <Input
                        ref={fileInputRef}
                        type="file"
                        name="images[]"
                        accept="image/*"
                        multiple
                        className="max-w-xs"
                    />
                    <Button type="submit" variant="outline" disabled={processing}>
                        <ImagePlus className="mr-2 h-4 w-4" />
                        {processing ? 'Хуулж байна...' : 'Зураг нэмэх'}
                    </Button>
                    {errors.images && (
                        <p className="text-sm text-destructive">{errors.images}</p>
                    )}
                </div>
            )}
        </Form>
    );
}

AdminLotteryEdit.layout = {
    breadcrumbs: [
        {
            title: 'Сугалаа удирдах',
            href: '/admin/lotteries',
        },
        {
            title: 'Засах',
        },
    ],
};
