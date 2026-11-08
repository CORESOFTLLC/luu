import { Head, Link } from '@inertiajs/react';
import { Plus, Upload, Pencil, Printer } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Lottery = {
    id: number;
    name: string;
    slug: string;
    price_per_ticket: number;
    total_tickets: number | null;
    sold_tickets: number;
    starts_at: string;
    draws_at: string;
    tickets_count: number;
};

type Props = {
    lotteries: Lottery[];
};

export default function AdminLotteryIndex({ lotteries }: Props) {
    return (
        <>
            <Head title="Сугалаа удирдах" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Сугалааны жагсаалт"
                        description="Сугалаа удирдах, statement импорт хийх"
                    />
                    <Button asChild>
                        <Link href="/admin/lotteries/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Шинэ сугалаа
                        </Link>
                    </Button>
                </div>

                <div className="space-y-4">
                    {lotteries.length === 0 ? (
                        <p className="py-10 text-center text-muted-foreground">
                            Сугалаа байхгүй байна.
                        </p>
                    ) : (
                        lotteries.map((lottery) => (
                            <LotteryRow key={lottery.id} lottery={lottery} />
                        ))
                    )}
                </div>
            </div>
        </>
    );
}

function LotteryRow({ lottery }: { lottery: Lottery }) {
    return (
        <div className="flex items-center justify-between rounded-lg border bg-card p-4">
            <div>
                <h3 className="font-bold">{lottery.name}</h3>
                <p className="text-sm text-muted-foreground">
                    {new Intl.NumberFormat('mn-MN').format(
                        lottery.price_per_ticket,
                    )}
                    ₮ · {lottery.tickets_count} сугалаа · {lottery.sold_tickets}
                    /{lottery.total_tickets ?? 'Хязгааргүй'}
                </p>
            </div>
            <div className="flex items-center gap-2">
                <Button variant="outline" size="sm" asChild>
                    <Link href={`/admin/lotteries/${lottery.id}/import`}>
                        <Upload className="mr-2 h-4 w-4" />
                        Импорт
                    </Link>
                </Button>
                <Button variant="outline" size="sm" asChild>
                    <a href={`/admin/lotteries/${lottery.id}/tickets/print`} target="_blank" rel="noopener">
                        <Printer className="mr-1 h-4 w-4" />
                        Сугалаа хэвлэх
                    </a>
                </Button>
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/admin/lotteries/${lottery.id}/edit`}>
                        <Pencil className="mr-2 h-4 w-4" />
                        Засах
                    </Link>
                </Button>
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/lotteries/${lottery.id}`}>Харах</Link>
                </Button>
            </div>
        </div>
    );
}

AdminLotteryIndex.layout = {
    breadcrumbs: [
        {
            title: 'Сугалаа удирдах',
            href: '/admin/lotteries',
        },
    ],
};
