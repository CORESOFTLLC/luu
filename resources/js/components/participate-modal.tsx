import confetti from 'canvas-confetti';
import { formatIban } from '@/lib/iban';
import { Check, X } from 'lucide-react';
import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Slider } from '@/components/ui/slider';
import type { LotteryData } from '@/types';

type Props = {
    lottery: LotteryData | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function ParticipateModal({
    lottery,
    open,
    onOpenChange,
}: Props) {
    const [phone, setPhone] = useState('');
    const [ticketCount, setTicketCount] = useState(1);
    const [showCelebration, setShowCelebration] = useState(false);

    const resetState = () => {
        setPhone('');
        setTicketCount(1);
        setShowCelebration(false);
    };

    const handleOpenChange = (open: boolean) => {
        if (!open) {
            resetState();
        }
        onOpenChange(open);
    };

    const fireConfetti = useCallback(() => {
        const duration = 3000;
        const end = Date.now() + duration;

        const frame = () => {
            confetti({
                particleCount: 3,
                angle: 60,
                spread: 55,
                origin: { x: 0, y: 0.7 },
                colors: ['#ff0000', '#ffd700', '#ff6347'],
            });
            confetti({
                particleCount: 3,
                angle: 120,
                spread: 55,
                origin: { x: 1, y: 0.7 },
                colors: ['#ff0000', '#ffd700', '#ff6347'],
            });

            if (Date.now() < end) {
                requestAnimationFrame(frame);
            }
        };

        frame();
    }, []);

    const handleTransferred = () => {
        setShowCelebration(true);
        fireConfetti();
    };

    if (!lottery) {
        return null;
    }

    const maxTickets = lottery.max_tickets_per_transaction ?? 15;
    const formattedMaxAmount = new Intl.NumberFormat('mn-MN').format(maxTickets * lottery.price_per_ticket);
    const totalAmount = ticketCount * lottery.price_per_ticket;
    const formattedTotal = new Intl.NumberFormat('mn-MN').format(totalAmount);

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-lg">
                {!showCelebration ? (
                    <>
                        <DialogHeader>
                            <DialogTitle className="text-center text-2xl">
                                {lottery.name}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="space-y-6">
                            {/* Phone */}
                            <div className="space-y-2">
                                <Label className="text-base font-semibold">
                                    Утасны дугаар
                                </Label>
                                <Input
                                    type="tel"
                                    placeholder="Утасны дугаар"
                                    value={phone}
                                    onChange={(e) => setPhone(e.target.value)}
                                    maxLength={8}
                                    className="text-center text-xl h-12"
                                />
                            </div>

                            {/* Ticket Count */}
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <Label className="text-base font-semibold">
                                        Сугалааны тоо
                                    </Label>
                                    <span className="rounded-md bg-primary px-3 py-1 text-base font-bold text-primary-foreground">
                                        {ticketCount} ширхэг
                                    </span>
                                </div>

                                <Slider
                                    value={[ticketCount]}
                                    onValueChange={(v) => setTicketCount(v[0])}
                                    min={1}
                                    max={maxTickets}
                                    step={1}
                                />
                                <p className="text-sm text-muted-foreground">
                                    Нэг гүйлгээнд дээд тал нь {maxTickets} ширхэг ({formattedMaxAmount} ₮)
                                </p>
                            </div>

                            {/* Result - always visible */}
                            <div className="rounded-xl bg-muted p-5 text-center space-y-3">
                                <p className="text-base text-muted-foreground">
                                    Утас:{' '}
                                    <span className="font-bold text-foreground">
                                        {phone || '--------'}
                                    </span>
                                </p>
                                <p className="text-base text-muted-foreground">
                                    Сугалааны тоо:{' '}
                                    <span className="font-bold text-foreground">
                                        {ticketCount} ширхэг
                                    </span>
                                </p>
                                <p className="text-3xl font-bold text-primary">
                                    {formattedTotal} ₮
                                </p>

                                {lottery.bank_account && (
                                    <div className="overflow-hidden rounded-lg border-2 border-[#174822]">
                                        <div className="bg-[#174822] px-4 py-3 flex items-center gap-3">
                                            <img
                                                alt="Хаан банк"
                                                className="size-10 rounded-lg bg-white/10 p-0.5"
                                                src="/bank/khanbank.svg"
                                            />
                                            <div>
                                                <p className="text-emerald-200 text-xs">
                                                    KHAN BANK
                                                </p>
                                            </div>
                                        </div>
                                        <div className="bg-[#174822]/5 px-4 py-3 text-center">
                                            <p className="font-mono text-3xl font-extrabold tracking-wider text-[#174822]">
                                                {formatIban(lottery.bank_account)}
                                            </p>
                                        </div>
                                    </div>
                                )}

                                <div className="rounded-lg border border-yellow-500 bg-yellow-50 dark:bg-yellow-950/30 p-3">
                                    <p className="text-sm font-bold text-yellow-800 dark:text-yellow-300">
                                        ⚠️ Гүйлгээний утга дээр утасны
                                        дугаараа заавал бичнэ үү!
                                    </p>
                                </div>
                            </div>

                            <Button
                                className="w-full h-12 text-lg font-bold"
                                onClick={handleTransferred}
                            >
                                <Check className="mr-2 h-5 w-5" />
                                Шилжүүлсэн
                            </Button>
                        </div>
                    </>
                ) : (
                    <div className="flex flex-col items-center justify-center py-8 space-y-6">
                        <div className="text-7xl animate-bounce">🎉</div>
                        <div className="text-center space-y-3">
                            <h2 className="text-3xl font-extrabold text-primary">
                                Баярлалаа!
                            </h2>
                            <p className="text-xl font-semibold text-muted-foreground">
                                Таны аз ивээг
                            </p>
                            <p className="text-2xl font-extrabold text-primary">
                                ✨ Атриш Луу ✨
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            className="mt-4 h-11 px-8 text-base"
                            onClick={() => handleOpenChange(false)}
                        >
                            <X className="mr-2 h-4 w-4" />
                            Хаах
                        </Button>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
