// Invoerveld met label en foutmelding. Wordt in alle formulieren gebruikt.
// Voorbeeld:  <FormField id="email" label="E-mailadres" type="email" value={email} onChange={...} error={errors.email} />
// Keuzelijst: <FormField as="select" id="species" label="Diersoort" ...><option>...</option></FormField>

export default function FormField({ id, label, error, as = 'input', children, ...inputProps }) {
  // as bepaalt welk element het wordt: 'input', 'select' of 'textarea'
  const Input = as

  return (
    <div className={error ? 'field has-error' : 'field'}>
      <label htmlFor={id}>{label}</label>
      <Input
        id={id}
        aria-invalid={error ? 'true' : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        {...inputProps}
      >
        {children}
      </Input>
      {error && (
        <p id={`${id}-error`} className="field-error">
          <span aria-hidden="true">⚠</span> {error}
        </p>
      )}
    </div>
  )
}
