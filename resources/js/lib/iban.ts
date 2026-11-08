// Display an account as "IBAN:<bank code> <account>".
// A plain account number (e.g. 5022421111) gets the Khan Bank code 53000500.
// A full IBAN already stored with its bank code (e.g. "MN00000500 1234567890") is shown as-is without the "MN" prefix.
export function formatIban(account: string): string {
    const value = account.trim().replace(/^MN\s*/i, );

    return /\s/.test(value) ? `IBAN:${value}` : `IBAN:53000500 ${value}`;
}
