/**
 * FieldRow helpers — compact form field components for PropertiesPanel.
 *
 * These reduce bundle size significantly by replacing repeated verbose
 * JSX patterns with a single function call.
 *
 * v1.4.0 — added default export `FieldRow` (generic wrapper with label + hint)
 * for use in ModuleProperties.js.
 *
 * @package WooTotalMenu
 * @since 1.3.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Generic FieldRow — wraps a label + children + optional hint.
 *
 * Use this when you need a custom control (e.g. mixed inputs) inside a row.
 * For single-input rows, prefer the named exports below (TextRow, etc.).
 *
 * @param {Object}   props       Component props.
 * @param {string}   props.label Row label.
 * @param {string}   [props.hint] Optional hint shown below the field.
 * @param {JSX.Element} props.children The control(s).
 * @return {JSX.Element} Row markup.
 */
export default function FieldRow({ label, hint, children }) {
        return (
                <div className="wtm-properties__row">
                        <label>{label}</label>
                        {children}
                        {hint && <em className="wtm-properties__hint">{hint}</em>}
                </div>
        );
}

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
