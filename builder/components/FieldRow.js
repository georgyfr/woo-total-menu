/**
 * FieldRow helpers — compact form field components for PropertiesPanel.
 *
 * These reduce bundle size significantly by replacing repeated verbose
 * JSX patterns with a single function call.
 *
 * @package WooTotalMenu
 * @since 1.3.0
 */

import { __ } from '@wordpress/i18n';

export function TextRow({ label, value, onChange, placeholder }) {
        return (
                <div className="wtm-properties__row">
                        <label>{label}</label>
                        <input type="text" value={value || ''} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} />
                </div>
        );
}

export function NumberRow({ label, value, onChange, min, max, step }) {
        return (
                <div className="wtm-properties__row">
                        <label>{label}</label>
                        <input
                                type="number"
                                min={min}
                                max={max}
                                step={step}
                                value={value}
                                onChange={(e) => onChange(parseInt(e.target.value, 10))}
                        />
                </div>
        );
}

export function CheckboxRow({ label, checked, onChange }) {
        return (
                <div className="wtm-properties__row">
                        <label>{label}</label>
                        <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />
                </div>
        );
}

export function SelectRow({ label, value, onChange, options }) {
        return (
                <div className="wtm-properties__row">
                        <label>{label}</label>
                        <select value={value} onChange={(e) => onChange(e.target.value)}>
                                {options.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                ))}
                        </select>
                </div>
        );
}

export function ColorRow({ label, value, onChange }) {
        return (
                <div className="wtm-properties__row">
                        <label>{label}</label>
                        <input type="color" value={value} onChange={(e) => onChange(e.target.value)} />
                </div>
        );
}

export function TextareaRow({ label, value, onChange, rows }) {
        return (
                <div className="wtm-properties__row">
                        <label>{label}</label>
                        <textarea rows={rows || 3} value={value || ''} onChange={(e) => onChange(e.target.value)} />
                </div>
        );
}
