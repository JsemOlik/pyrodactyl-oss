import styled from 'styled-components';

export type BillingInvoice = {
    id: string;
    number?: string;
    date: string; // ISO
    amount: number;
    currency: string;
    status: 'paid' | 'open' | 'void' | 'uncollectible' | 'draft';
    downloadUrl?: string;
};

const StatusIndicatorBox = styled.div<{ $status: BillingInvoice['status'] | undefined }>`
    background: #ffffff11;
    border: 1px solid #ffffff12;
    transition: all 250ms ease-in-out;
    padding: 1.25rem 1.5rem;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;

    &:hover {
        border: 1px solid #ffffff19;
        background: #ffffff19;
        transition-duration: 0ms;
    }

    & .status-bar {
        width: 10px;
        height: 10px;
        min-width: 10px;
        min-height: 10px;
        background-color: #ffffff11;
        border-radius: 9999px;
        transition: all 250ms ease-in-out;

        box-shadow: ${({ $status }) =>
            $status === 'paid'
                ? '0 0 12px 1px #43C760'
                : $status === 'open'
                  ? '0 0 12px 1px #c7aa43'
                  : '0 0 12px 1px #C74343'};

        background: ${({ $status }) =>
            $status === 'paid'
                ? `linear-gradient(180deg, #91FFA9 0%, #43C760 100%)`
                : $status === 'open'
                  ? `linear-gradient(180deg, #c7aa43 0%, #c7aa43 100%)`
                  : `linear-gradient(180deg, #C74343 0%, #C74343 100%)`};
    }
`;

const formatMoney = (amount: number, currency: string) =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount);

export function BillingInvoiceRow({ invoice }: { invoice: BillingInvoice }) {
    const date = new Date(invoice.date).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
    const amount = formatMoney(invoice.amount, invoice.currency.toUpperCase());

    return (
        <StatusIndicatorBox $status={invoice.status}>
            <div className='flex items-center gap-2'>
                <div className='status-bar' />
                <div className='flex flex-col'>
                    <div className='flex items-center gap-2'>
                        <p className='text-sm tracking-tight font-semibold'>Invoice {invoice.number || invoice.id}</p>
                        <span className='text-[11px] px-2 py-0.5 rounded-full bg-white/10 text-white/80'>
                            {invoice.status.toUpperCase()}
                        </span>
                    </div>
                    <div className='text-xs text-[#ffffffaa]'>Issued {date}</div>
                </div>
            </div>

            <div className='flex items-center gap-2'>
                <span className='text-sm font-bold'>{amount}</span>
                {invoice.downloadUrl && (
                    <a
                        href={invoice.downloadUrl}
                        target='_blank'
                        rel='noreferrer'
                        className='inline-flex items-center gap-2 rounded-full bg-[#3f3f46] hover:bg-[#52525b] text-white px-3 py-1.5 text-xs font-semibold transition-colors'
                    >
                        Download
                    </a>
                )}
            </div>
        </StatusIndicatorBox>
    );
}
