export type LotteryImageData = {
    id: number;
    path: string;
    sort_order: number;
};

export type LotteryData = {
    id: number;
    name: string;
    slug: string;
    image: string | null;
    description: string | null;
    bank_account: string | null;
    bank_name: string | null;
    location: string | null;
    is_active: boolean;
    images?: LotteryImageData[];
    price_per_ticket: number;
    total_tickets?: number | null;
    max_tickets_per_transaction?: number | null;
    show_ticket_numbers?: boolean;
    sold_tickets?: number;
    completion?: number;
    is_finished: boolean;
    starts_at: string;
    draws_at: string;
};

export type LotteryTicketData = {
    id: number;
    ticket_number?: number;
    phone: string;
    transaction_id: number | null;
    created_at: string;
    transaction?: {
        id: number;
        amount: number;
        description: string;
        transacted_at: string;
    };
};
