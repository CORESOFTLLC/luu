import { Head, Link, router } from '@inertiajs/react';
import { ArrowUpDown, ArrowUp, ArrowDown, Search, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState, useCallback, useRef } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Lottery = {
    id: number;
    name: string;
    price_per_ticket: number;
    show_ticket_numbers: boolean;
};

type BankEntry = {
    id: number;
    phone: string;
    amount: number;
    ticket_number: number | null;
    is_excluded: boolean;
    created_at: string;
    transaction?: {
        id: number;
        bank_reference: string;
        description: string;
        transacted_at: string;
    };
};

type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    lottery: Lottery;
    bankEntries: PaginatedData<BankEntry>;
    filters: {
        search: string;
        status: string;
        sort_by: string;
        sort_dir: 'asc' | 'desc';
    };
};

export default function AdminLotteryBank({ lottery, bankEntries, filters }: Props) {
    const [searchValue, setSearchValue] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [sortBy, setSortBy] = useState(filters.sort_by ?? 'ticket_number');
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>(filters.sort_dir ?? 'asc');
    const searchTimeout = useRef<ReturnType<typeof setTimeout>>();

    const navigate = useCallback((params: Record<string, string | undefined>) => {
        router.get(
            `/admin/lotteries/${lottery.id}/bank`,
            params,
            { preserveState: true, replace: true },
        );
    }, [lottery.id]);

    const handleSearch = useCallback((value: string) => {
        setSearchValue(value);
        clearTimeout(searchTimeout.current);
        searchTimeout.current = setTimeout(() => {
            navigate({
                search: value || undefined,
                status: statusFilter || undefined,
                sort_by: sortBy,
                sort_dir: sortDir,
            });
        }, 300);
    }, [navigate, statusFilter, sortBy, sortDir]);

    const handleStatusFilter = (value: string) => {
        setStatusFilter(value);
        navigate({
            search: searchValue || undefined,
            status: value || undefined,
            sort_by: sortBy,
            sort_dir: sortDir,
        });
    };

    const handleSort = (column: string) => {
        const nextDir: 'asc' | 'desc' = sortBy === column && sortDir === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortDir(nextDir);
        navigate({
            search: searchValue || undefined,
            status: statusFilter || undefined,
            sort_by: column,
            sort_dir: nextDir,
        });
    };

    const SortIcon = ({ column }: { column: string }) => {
        if (sortBy !== column) return <ArrowUpDown className="ml-1 inline h-3 w-3 opacity-40" />;
        return sortDir === 'asc' ? <ArrowUp className="ml-1 inline h-3 w-3" /> : <ArrowDown className="ml-1 inline h-3 w-3" />;
    };

    const formatDate = (dateStr: string) =>
        new Date(dateStr).toLocaleDateString('mn-MN', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit',
        });

    const PaginationControls = ({ paginator }: { paginator: PaginatedData<unknown> }) => {
        if (paginator.last_page <= 1) return null;
        const current = Object.fromEntries(new URLSearchParams(window.location.search));
        return (
            <div className="flex items-center justify-between pt-4">
                <p className="text-sm text-muted-foreground">
                    {paginator.from}–{paginator.to} / {paginator.total}
                </p>
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={paginator.current_page <= 1}
                        onClick={() => router.get(`/admin/lotteries/${lottery.id}/bank`, { ...current, page: String(paginator.current_page - 1) }, { preserveState: true, replace: true })}
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <span className="px-2 text-sm">{paginator.current_page} / {paginator.last_page}</span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={paginator.current_page >= paginator.last_page}
                        onClick={() => router.get(`/admin/lotteries/${lottery.id}/bank`, { ...current, page: String(paginator.current_page + 1) }, { preserveState: true, replace: true })}
                    >
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        );
    };

    return (
        <>
            <Head title={`${lottery.name} - Сугалааны банк`} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Сугалааны банк"
                    description={lottery.name}
                />

                {/* Tab nav */}
                <div className="flex gap-1 border-b">
                    <Link
                        href={`/admin/lotteries/${lottery.id}/import`}
                        className="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                    >
                        Гүйлгээ
                    </Link>
                    <Link
                        href={`/admin/lotteries/${lottery.id}/bank`}
                        className="border-b-2 border-primary px-4 py-2 text-sm font-medium text-foreground"
                    >
                        Сугалааны банк
                    </Link>
                    <Link
                        href={`/admin/lotteries/${lottery.id}/tickets`}
                        className="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                    >
                        Сугалаанууд
                    </Link>
                </div>

                {/* Search & Filter */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative max-w-sm flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Утас, дансны дугаар, сугалаа..."
                            value={searchValue}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <div className="flex items-center gap-1">
                        <Button variant={statusFilter === '' ? 'default' : 'outline'} size="sm" onClick={() => handleStatusFilter('')}>
                            Бүгд
                        </Button>
                        <Button variant={statusFilter === 'active' ? 'default' : 'outline'} size="sm" onClick={() => handleStatusFilter('active')}>
                            Идэвхтэй
                        </Button>
                        <Button variant={statusFilter === 'excluded' ? 'default' : 'outline'} size="sm" onClick={() => handleStatusFilter('excluded')}>
                            Сугалаа биш
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Сугалааны банк</CardTitle>
                        <CardDescription>
                            Нийт {bankEntries.total} бүртгэл — {new Intl.NumberFormat('mn-MN').format(lottery.price_per_ticket)}₮ тутамд сугалаа үүсгэсэн
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="cursor-pointer select-none" onClick={() => handleSort('ticket_number')}>
                                        Сугалаа <SortIcon column="ticket_number" />
                                    </TableHead>
                                    <TableHead className="cursor-pointer select-none" onClick={() => handleSort('phone')}>
                                        Утас <SortIcon column="phone" />
                                    </TableHead>
                                    <TableHead className="cursor-pointer select-none" onClick={() => handleSort('amount')}>
                                        Дүн <SortIcon column="amount" />
                                    </TableHead>
                                    <TableHead className="cursor-pointer select-none" onClick={() => handleSort('description')}>
                                        Гүйлгээний утга <SortIcon column="description" />
                                    </TableHead>
                                    <TableHead className="cursor-pointer select-none" onClick={() => handleSort('transacted_at')}>
                                        Огноо <SortIcon column="transacted_at" />
                                    </TableHead>
                                    <TableHead className="cursor-pointer select-none" onClick={() => handleSort('is_excluded')}>
                                        Төлөв <SortIcon column="is_excluded" />
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {bankEntries.data.map((entry) => (
                                    <TableRow
                                        key={entry.id}
                                        className={entry.is_excluded ? 'opacity-40' : !entry.ticket_number ? 'bg-yellow-50 dark:bg-yellow-950/20' : ''}
                                    >
                                        <TableCell className="font-mono font-bold">
                                            {entry.is_excluded ? (
                                                <span className="text-muted-foreground line-through">—</span>
                                            ) : entry.ticket_number ? (
                                                <span className="text-primary">{lottery.show_ticket_numbers ? `#${entry.ticket_number}` : 'Сугалаа'}</span>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell>{entry.phone}</TableCell>
                                        <TableCell>
                                            {new Intl.NumberFormat('mn-MN').format(entry.amount)}₮
                                        </TableCell>
                                        <TableCell className="max-w-[200px] truncate text-xs">
                                            {entry.transaction?.description}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {entry.transaction?.transacted_at ? formatDate(entry.transaction.transacted_at) : '—'}
                                        </TableCell>
                                        <TableCell>
                                            {entry.is_excluded ? (
                                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                                    Сугалаа биш
                                                </span>
                                            ) : entry.ticket_number ? (
                                                <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                    Сугалаа үүссэн
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                    Дутуу ({new Intl.NumberFormat('mn-MN').format(lottery.price_per_ticket - entry.amount)}₮)
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <PaginationControls paginator={bankEntries} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminLotteryBank.layout = {
    breadcrumbs: [
        { title: 'Сугалаа удирдах', href: '/admin/lotteries' },
        { title: 'Сугалааны банк' },
    ],
};
