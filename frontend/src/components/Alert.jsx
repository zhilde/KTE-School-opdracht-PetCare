// Melding bij succes, fout of lege resultaten (FE8).
// Altijd icoon + tekst, zodat het niet alleen met kleur te begrijpen is.

const icons = { success: '✓', error: '⚠', empty: 'ℹ' }

export default function Alert({ type = 'success', children, onClose }) {
  // Schermlezers lezen een fout meteen voor (alert), succes rustig (status)
  const role = type === 'error' ? 'alert' : 'status'

  return (
    <div className={`alert alert-${type}`} role={role}>
      <span aria-hidden="true">{icons[type]}</span>
      <div>{children}</div>
      {onClose && (
        <button type="button" className="alert-close" onClick={onClose} aria-label="Melding sluiten">
          ✕
        </button>
      )}
    </div>
  )
}
