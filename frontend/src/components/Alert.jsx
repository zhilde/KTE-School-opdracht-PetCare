import { useEffect, useRef } from 'react'
import { CONNECTION_ERROR, TECHNICAL_ERROR } from '../api.js'

// Melding bij succes, fout of lege resultaten (FE8).
// Altijd icoon + tekst, zodat het niet alleen met kleur te begrijpen is.

const icons = { success: '✓', error: '⚠', empty: 'ℹ' }

export default function Alert({ type = 'success', children, onClose }) {
  const ref = useRef(null)

  // Schermlezers lezen een fout meteen voor (alert), succes rustig (status)
  const role = type === 'error' ? 'alert' : 'status'

  // Technische fout (server uit of serverfout)? Dan knop "Opnieuw proberen" (ontwerp §9)
  const isTechnical = type === 'error' && (children === CONNECTION_ERROR || children === TECHNICAL_ERROR)

  // Succes- en foutmeldingen in beeld scrollen, zodat je ze ook op een lange pagina ziet
  useEffect(() => {
    if (type !== 'empty') {
      ref.current?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
    }
  }, [type])

  return (
    <div ref={ref} className={`alert alert-${type}`} role={role}>
      <span aria-hidden="true">{icons[type]}</span>
      <div>
        {children}
        {isTechnical && (
          <>
            {' '}
            {/* Pagina opnieuw laden = alle gegevens opnieuw ophalen */}
            <button type="button" className="btn btn-secondary btn-small" onClick={() => window.location.reload()}>
              Opnieuw proberen
            </button>
          </>
        )}
      </div>
      {onClose && (
        <button type="button" className="alert-close" onClick={onClose} aria-label="Melding sluiten">
          ✕
        </button>
      )}
    </div>
  )
}
