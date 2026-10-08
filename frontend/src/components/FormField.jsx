// Invoerveld met label en foutmelding. Wordt in alle formulieren gebruikt.
// Voorbeeld: <FormField id="email" label="E-mailadres" type="email" value={email} onChange={...} error={errors.email} />

export default function FormField({ id, label, error, ...inputProps }) {
  return (
    <div className={error ? 'field has-error' : 'field'}>
      <label htmlFor={id}>{label}</label>
      <input
        id={id}
        aria-invalid={error ? 'true' : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        {...inputProps}
      />
      {error && (
        <p id={`${id}-error`} className="field-error">
          <span aria-hidden="true">⚠</span> {error}
        </p>
      )}
    </div>
  )
}
