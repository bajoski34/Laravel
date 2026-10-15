import { useMemo, useState } from 'react';
import { filterBanks } from './admin';
import { Chevron } from './icons';
import type { FlutterwaveBank } from './types';

interface Props {
    /** From banks()->options($country) on the server. */
    banks: FlutterwaveBank[];
    /** The form field name; it submits the bank's code. */
    name?: string;
    /** Controlled value (a bank code). Use with onChange, or use defaultValue instead. */
    value?: string;
    defaultValue?: string;
    onChange?: (code: string, bank: FlutterwaveBank | undefined) => void;
    /** Show a search box. Defaults to on when there are more than 12 banks. */
    searchable?: boolean;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    id?: string;
    /** Added to the wrapper. */
    className?: string;
}

/** A dropdown of banks: each option shows the bank's name and its value is the bank code. */
export function BankSelect({ banks, name = 'account_bank', value, defaultValue = '', onChange, searchable, required = true, disabled = false, placeholder = 'Choose a bank', id, className }: Props) {
    const [term, setTerm] = useState('');
    const [own, setOwn] = useState(defaultValue);
    const selected = value ?? own;
    const visible = useMemo(() => filterBanks(banks, term, selected), [banks, term, selected]);
    const showSearch = searchable ?? banks.length > 12;

    const pick = (code: string) => {
        setOwn(code);
        onChange?.(code, banks.find((bank) => bank.code === code));
    };

    return (
        <div className={`flw-bank-select${className ? ` ${className}` : ''}`}>
            {showSearch && (
                <input type="search" className="flw-input" value={term} onChange={(event) => setTerm(event.target.value)} placeholder={`Search ${banks.length} banks`} aria-label="Search banks" autoComplete="off" />
            )}
            <div className="flw-select">
                <select id={id} name={name} value={selected} required={required} disabled={disabled || banks.length === 0} onChange={(event) => pick(event.target.value)} className="flw-input">
                    <option value="">{placeholder}</option>
                    {visible.map((bank) => (
                        <option key={bank.code} value={bank.code}>{bank.name}</option>
                    ))}
                </select>
                <Chevron />
            </div>
        </div>
    );
}
