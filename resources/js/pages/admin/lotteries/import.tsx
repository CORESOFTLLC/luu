import { Form, Head, Link, router } from '@inertiajs/react';
import { Upload, FileSpreadsheet, Trash2, Search, ChevronLeft, ChevronRight, Ban, RotateCcw, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-react';
import { useRef, useState, useCallback } from 'react';
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
    total_tickets: number | null;
    sold_tickets: number;
    tickets_count: number;
    price_per_ticket: number;
    total_amount: number;
    refund_total: number;
    excluded_count: number;
    active_count: number;
    transactions_count: number;
};

type Transaction = {
    id: number;
    bank_reference: string;
    phone: string;
    amount: number;
    refund_amount: number;
    description: string;
    transacted_at: string;
    is_excluded: boolean;
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
    recentTransactions: PaginatedData<Transaction>;
    filters: {
        search: string;
        status: string;
        sort_by: string;
        sort_dir: 'asc' | 'desc';
    };
};

export default function AdminLotteryImport({
    lottery,
    recentTransactions,
    filters,
}: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [searchValue, setSearchValue] = useState(filters.search ?? '');
    const [statusFilter, setStatusFilter] = useState(filters.status ?? '');
    const [sortBy, setSortBy] = useState(filters.sort_by ?? 'transacted_at');
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>(filters.sort_dir ?? 'desc');
    const searchTimeout = useRef<ReturnType<typeof setTimeout>>();

    const handleSearch = useCallback((value: string) => {
        setSearchValue(value);
        clearTimeout(searchTimeout.current);
        searchTimeout.current = setTimeout(() => {
            router.get(
                `/admin/lotteries/${lottery.id}/import`,
                {
                    search: value || undefined,
                    status: statusFilter || undefined,
                    sort_by: sortBy,
                    sort_dir: sortDir,
                },
                { preserveState: true, replace: true },
            );
        }, 300);
    }, [lottery.id, statusFilter, sortBy, sortDir]);

    const handleStatusFilter = (value: string) => {
        setStatusFilter(value);
        router.get(
            `/admin/lotteries/${lottery.id}/import`,
            {
                search: searchValue || undefined,
                status: value || undefined,
                sort_by: sortBy,
                sort_dir: sortDir,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleSort = (column: string) => {
        const nextDir: 'asc' | 'desc' = sortBy === column && sortDir === 'asc' ? 'desc' : 'asc';
        setSortBy(column);
        setSortDir(nextDir);
        router.get(
            `/admin/lotteries/${lottery.id}/import`,
            {
                search: searchValue || undefined,
                status: statusFilter || undefined,
                sort_by: column,
                sort_dir: nextDir,
            },
            { preserveState: true, replace: true },
        );
    };

    const SortIcon = ({ column }: { column: string }) => {
        if (sortBy !== column) {
            return <ArrowUpDown className="ml-1 inline h-3 w-3 opacity-40" />;
        }

        return sortDir === 'asc'
            ? <ArrowUp className="ml-1 inline h-3 w-3" />
            : <ArrowDown className="ml-1 inline h-3 w-3" />;
    };

    const PaginationControls = ({ paginator, paramName }: { paginator: PaginatedData<unknown>; paramName: string }) => {
        if (paginator.last_page <= 1) return null;
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
                        onClick={() => router.get(
                            `/admin/lotteries/${lottery.id}/import`,
                            { ...Object.fromEntries(new URLSearchParams(window.location.search)), [paramName]: paginator.current_page - 1 },
                            { preserveState: true, replace: true },
                        )}
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <span className="px-2 text-sm">
                        {paginator.current_page} / {paginator.last_page}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={paginator.current_page >= paginator.last_page}
                        onClick={() => router.get(
                            `/admin/lotteries/${lottery.id}/import`,
                            { ...Object.fromEntries(new URLSearchParams(window.location.search)), [paramName]: paginator.current_page + 1 },
                            { preserveState: true, replace: true },
                        )}
                    >
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        );
    };

    const formatDate = (dateStr: string) => {
        return new Date(dateStr).toLocaleDateString('mn-MN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <>
            <Head title={`${lottery.name} - Хуулга импорт`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Хаан банкны хуулга импорт"
                        description={lottery.name}
                    />
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => {
                            if (confirm('Бүх гүйлгээ, сугалаа, банкны бүртгэлийг устгах уу?')) {
                                router.delete(`/admin/lotteries/${lottery.id}/import`);
                            }
                        }}
                    >
                        <Trash2 className="mr-2 h-4 w-4" />
                        Бүгдийг арилгах
                    </Button>
                </div>

                {/* Tab nav */}
                <div className="flex gap-1 border-b">
                    <Link
                        href={`/admin/lotteries/${lottery.id}/import`}
                        className="border-b-2 border-primary px-4 py-2 text-sm font-medium text-foreground"
                    >
                        Гүйлгээ
                    </Link>
                    <Link
                        href={`/admin/lotteries/${lottery.id}/bank`}
                        className="border-b-2 border-transparent px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
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

                {/* Stats */}
                <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Нийт сугалаа</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {lottery.tickets_count}
                                <span className="text-sm font-normal text-muted-foreground">
                                    {' '}
                                    / {lottery.total_tickets ?? 'Хязгааргүй'}
                                </span>
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Зарагдсан</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {lottery.sold_tickets}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Нийт дүн</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {new Intl.NumberFormat('mn-MN').format(lottery.total_amount)}
                                <span className="text-sm font-normal text-muted-foreground">₮</span>
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Гүйлгээ</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold">
                                {lottery.transactions_count}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1">
                                <span className="inline-block size-2 rounded-full bg-green-500" />
                                Идэвхтэй
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                {lottery.active_count}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription className="flex items-center gap-1">
                                <span className="inline-block size-2 rounded-full bg-gray-400" />
                                Цуцлагдсан
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold text-muted-foreground">
                                {lottery.excluded_count}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Upload */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileSpreadsheet className="h-5 w-5" />
                            Excel хуулга оруулах
                        </CardTitle>
                        <CardDescription>
                            Хаан банкнаас татсан .xlsx, .xls, .csv файлыг
                            сонгоно уу. Утасны дугааргаар сугалаа автоматаар
                            үүснэ.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={`/admin/lotteries/${lottery.id}/import`}
                            method="post"
                            encType="multipart/form-data"
                            options={{ forceFormData: true }}
                        >
                            {({ processing, errors }) => (
                                <div className="flex items-center gap-4">
                                    <Input
                                        ref={fileInputRef}
                                        type="file"
                                        name="file"
                                        accept=".xlsx,.xls,.csv"
                                        className="max-w-sm"
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                    >
                                        <Upload className="mr-2 h-4 w-4" />
                                        {processing
                                            ? 'Импорт хийж байна...'
                                            : 'Импорт хийх'}
                                    </Button>
                                    {errors.file && (
                                        <p className="text-sm text-destructive">
                                            {errors.file}
                                        </p>
                                    )}
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {/* Search & Filter */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative max-w-sm flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Утас, дугаар, лавлах..."
                            value={searchValue}
                            onChange={(e) => handleSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <div className="flex items-center gap-1">
                        <Button
                            variant={statusFilter === '' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleStatusFilter('')}
                        >
                            Бүгд
                        </Button>
                        <Button
                            variant={statusFilter === 'active' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleStatusFilter('active')}
                        >
                            Идэвхтэй
                        </Button>
                        <Button
                            variant={statusFilter === 'excluded' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleStatusFilter('excluded')}
                        >
                            Сугалаа биш
                        </Button>
                        <Button
                            variant={statusFilter === 'refund' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleStatusFilter('refund')}
                        >
                            Буцаах ({new Intl.NumberFormat('mn-MN').format(lottery.refund_total)}₮)
                        </Button>
                    </div>
                </div>

                {/* Recent Transactions */}
                {recentTransactions.data.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Сүүлийн гүйлгээнүүд</CardTitle>
                            <CardDescription>
                                Нийт {recentTransactions.total} гүйлгээ
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="cursor-pointer select-none" onClick={() => handleSort('bank_reference')}>
                                            Лавлах <SortIcon column="bank_reference" />
                                        </TableHead>
                                        <TableHead className="cursor-pointer select-none" onClick={() => handleSort('phone')}>
                                            Утас <SortIcon column="phone" />
                                        </TableHead>
                                        <TableHead className="cursor-pointer select-none" onClick={() => handleSort('amount')}>
                                            Дүн <SortIcon column="amount" />
                                        </TableHead>
                                        <TableHead className="cursor-pointer select-none" onClick={() => handleSort('refund_amount')}>
                                            Буцаах дүн <SortIcon column="refund_amount" />
                                        </TableHead>
                                        <TableHead className="cursor-pointer select-none" onClick={() => handleSort('description')}>
                                            Гүйлгээний утга <SortIcon column="description" />
                                        </TableHead>
                                        <TableHead className="cursor-pointer select-none" onClick={() => handleSort('transacted_at')}>
                                            Банкаар шилжүүлсэн огноо <SortIcon column="transacted_at" />
                                        </TableHead>
                                        <TableHead className="cursor-pointer select-none" onClick={() => handleSort('is_excluded')}>
                                            Төлөв <SortIcon column="is_excluded" />
                                        </TableHead>
                                        <TableHead className="w-[80px]">Үйлдэл</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentTransactions.data.map((tx) => (
                                        <TableRow key={tx.id} className={tx.is_excluded ? 'opacity-40' : ''}>
                                            <TableCell className="font-mono text-xs">
                                                {tx.bank_reference}
                                            </TableCell>
                                            <TableCell>{tx.phone}</TableCell>
                                            <TableCell>
                                                {new Intl.NumberFormat(
                                                    'mn-MN',
                                                ).format(tx.amount)}
                                                ₮
                                            </TableCell>
                                            <TableCell className={tx.refund_amount > 0 ? 'font-semibold text-red-600' : 'text-muted-foreground'}>
                                                {tx.refund_amount > 0
                                                    ? `${new Intl.NumberFormat('mn-MN').format(tx.refund_amount)}₮`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell className="max-w-[200px] truncate text-xs">
                                                {tx.description}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {tx.transacted_at ? formatDate(tx.transacted_at) : '—'}
                                            </TableCell>
                                            <TableCell>
                                                {tx.is_excluded ? (
                                                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                                        Цуцлагдсан
                                                    </span>
                                                ) : (
                                                    <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                        Идэвхтэй
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-7 px-2"
                                                    onClick={() => router.post(`/admin/lotteries/${lottery.id}/transactions/${tx.id}/toggle-exclude`, {}, { preserveState: true })}
                                                >
                                                    {tx.is_excluded ? (
                                                        <RotateCcw className="h-3.5 w-3.5 text-green-600" />
                                                    ) : (
                                                        <Ban className="h-3.5 w-3.5 text-destructive" />
                                                    )}
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <PaginationControls paginator={recentTransactions} paramName="transactions_page" />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

AdminLotteryImport.layout = {
    breadcrumbs: [
        {
            title: 'Сугалаа удирдах',
            href: '/admin/lotteries',
        },
        {
            title: 'Хуулга импорт',
        },
    ],
};
