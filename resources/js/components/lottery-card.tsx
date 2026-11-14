import { Link } from '@inertiajs/react';
import { ArrowRight, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { LotteryData } from '@/types';

type Props = {
    lottery: LotteryData;
    onParticipate: (lottery: LotteryData) => void;
};

export default function LotteryCard({ lottery, onParticipate }: Props) {
    const formattedPrice = new Intl.NumberFormat('mn-MN').format(
        lottery.price_per_ticket,
    );

    return (
        <Card className="overflow-hidden">
            <div className="relative">
                {lottery.image ? (
                    <img
                        src={`/storage/${lottery.image}`}
                        alt={lottery.name}
                        className="aspect-video w-full object-cover"
                    />
                ) : (
                    <div className="flex aspect-video w-full items-center justify-center bg-muted">
                        <span className="text-4xl">🎰</span>
                    </div>
                )}

                <span className="absolute top-3 right-3 rounded-full bg-primary px-3 py-1 text-sm font-bold text-primary-foreground">
                    {formattedPrice} ₮
                </span>

                <div className="absolute right-3 bottom-3 flex items-center gap-2">
                    {lottery.is_finished && (
                        <span className="rounded bg-blue-500 px-2 py-0.5 text-xs font-medium text-white">
                            Дууссагүй байгаа
                        </span>
                    )}
                </div>
            </div>

            <CardContent className="space-y-3 p-4">
                <h3 className="text-lg font-bold">{lottery.name}</h3>

                <div className="flex flex-col gap-2">
                    <Button
                        className="w-full"
                        onClick={() => onParticipate(lottery)}
                    >
                        <ArrowRight className="mr-2 h-4 w-4" />
                        Оролцох
                    </Button>

                    <Button variant="outline" className="w-full" asChild>
                        <Link href={`/lotteries/${lottery.id}`}>
                            <Search className="mr-2 h-4 w-4" />
                            Сугалаа шалгах
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
