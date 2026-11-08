import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

export default function AdminLotteryCreate() {
    const [unlimited, setUnlimited] = useState(false);
    return (
        <>
            <Head title="Шинэ сугалаа" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Шинэ сугалаа үүсгэх"
                    description="Сугалааны мэдээлэл оруулна уу"
                />

                <Form
                    action="/admin/lotteries"
                    method="post"
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
                                    placeholder="Сугалааны нэр"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="image">Зураг</Label>
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
                                <Textarea
                                    id="description"
                                    name="description"
                                    rows={5}
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
                                    placeholder="5022421111"
                                />
                                <InputError message={errors.bank_account} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_name">Банкны нэр</Label>
                                <Input
                                    id="bank_name"
                                    name="bank_name"
                                    placeholder="Хаан банк"
                                />
                                <InputError message={errors.bank_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="location">Байршил</Label>
                                <Input
                                    id="location"
                                    name="location"
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
                                    defaultValue={15000}
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
                                    min={1}
                                    placeholder="10000"
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
                                    defaultValue={15}
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
                                    defaultChecked={false}
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
                                />
                                <InputError message={errors.draws_at} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                            >
                                {processing && <Spinner />}
                                Үүсгэх
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AdminLotteryCreate.layout = {
    breadcrumbs: [
        {
            title: 'Сугалаа удирдах',
            href: '/admin/lotteries',
        },
        {
            title: 'Шинэ сугалаа',
            href: '/admin/lotteries/create',
        },
    ],
};
