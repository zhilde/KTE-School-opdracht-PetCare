import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api.js'
import { speciesLabel } from '../constants.js'
import Alert from '../components/Alert.jsx'

// De drie soorten verzorging. Medicatie en gedrag zijn gevoelig (besluit C, FE7).
const CARE_TYPES = [
  { type: 'voeding', label: 'Voeding', sensitive: false },
  { type: 'medicatie', label: 'Medicatie', sensitive: true },
  { type: 'gedrag', label: 'Gedrag', sensitive: true },
]

// '2026-10-08 14:02:00' -> '08-10-2026 14:02'
function formatDateTime(value) {
  const [date, time] = value.split(' ')
  const [year, month, day] = date.split('-')
  return `${day}-${month}-${year} ${time.slice(0, 5)}`
}

const roleNames = { owner: 'eigenaar', employee: 'medewerker', admin: 'beheerder' }

// Scherm "Dierdetails" (FE5): verzorgingsinstructies bekijken en wijzigen + wijzigingsgeschiedenis
export default function PetDetail() {
  const { id } = useParams()

  const [pet, setPet] = useState(null)
  const [instructions, setInstructions] = useState([])
  const [changes, setChanges] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState('')
  const [errorMessage, setErrorMessage] = useState('')

  // Welk type wordt nu gewijzigd (bv. 'medicatie'), en de tekst in het tekstvak
  const [editing, setEditing] = useState(null)
  const [text, setText] = useState('')
  const [fieldError, setFieldError] = useState('')
  const [busy, setBusy] = useState(false)

  // useCallback: de functie wordt alleen opnieuw gemaakt als het id verandert.
  // Zo kan hij veilig in useEffect gebruikt worden, en ook na het opslaan.
  const fetchCare = useCallback(() => {
    return api(`/pets/${id}/care`)
      .then((data) => {
        setPet(data.pet)
        setInstructions(data.instructions)
        setChanges(data.changes)
      })
      .catch((error) => setErrorMessage(error.message))
      .finally(() => setLoading(false))
  }, [id])

  useEffect(() => {
    fetchCare()
  }, [fetchCare])

  // Tekst van een type opzoeken (of leeg als er nog niks is)
  function textOf(type) {
    return instructions.find((i) => i.type === type)?.text ?? ''
  }

  function startEdit(type) {
    setEditing(type)
    setText(textOf(type))
    setFieldError('')
    setMessage('')
  }

  async function save(event) {
    event.preventDefault()
    setFieldError('')
    setErrorMessage('')
    setBusy(true)

    try {
      const data = await api(`/pets/${id}/care`, { method: 'PUT', body: { type: editing, text } })
      setMessage(data.message)
      setEditing(null)
      await fetchCare() // nieuwe tekst + nieuwe regel in de geschiedenis ophalen
    } catch (error) {
      setFieldError(error.errors?.text ?? '')
      if (!error.errors?.text) setErrorMessage(error.message)
    } finally {
      setBusy(false)
    }
  }

  if (loading) {
    return <p className="loading">Bezig met laden…</p>
  }

  if (!pet) {
    return (
      <section>
        <Alert type="error">{errorMessage || 'Dier niet gevonden.'}</Alert>
        <Link to="/dieren">← Terug naar mijn dieren</Link>
      </section>
    )
  }

  return (
    <section>
      <p><Link to="/dieren">← Terug naar mijn dieren</Link></p>
      <h1>{pet.name} <span className="muted">({speciesLabel(pet.species)})</span></h1>

      {message && <Alert type="success" onClose={() => setMessage('')}>{message}</Alert>}
      {errorMessage && <Alert type="error" onClose={() => setErrorMessage('')}>{errorMessage}</Alert>}

      {CARE_TYPES.map(({ type, label, sensitive }) => (
        <div key={type} className="card">
          <div className="instruction-head">
            <h2>
              {label}{' '}
              {sensitive && <span className="badge badge-sensitive">🔒 Gevoelig</span>}
            </h2>
            {editing !== type && (
              <button type="button" className="btn btn-secondary btn-small" onClick={() => startEdit(type)}>
                {textOf(type) ? 'Wijzigen' : 'Toevoegen'}
              </button>
            )}
          </div>

          {editing === type ? (
            <form onSubmit={save} noValidate>
              <div className={fieldError ? 'field has-error' : 'field'}>
                <label htmlFor={`text-${type}`}>{label} *</label>
                <textarea
                  id={`text-${type}`}
                  rows={3}
                  maxLength={2000}
                  value={text}
                  onChange={(e) => setText(e.target.value)}
                  aria-invalid={fieldError ? 'true' : undefined}
                  aria-describedby={fieldError ? `text-${type}-error` : undefined}
                />
                {fieldError && (
                  <p id={`text-${type}-error`} className="field-error"><span aria-hidden="true">⚠</span> {fieldError}</p>
                )}
              </div>
              <div className="actions">
                <button type="submit" className="btn btn-primary" disabled={busy}>
                  {busy ? 'Bezig met opslaan…' : 'Opslaan'}
                </button>
                <button type="button" className="btn btn-secondary" onClick={() => setEditing(null)}>Annuleren</button>
              </div>
            </form>
          ) : (
            <p>{textOf(type) || <span className="muted">Nog niets ingevuld.</span>}</p>
          )}
        </div>
      ))}

      {/* Wijzigingsgeschiedenis (FE5): wie, wat en wanneer */}
      <details className="card">
        <summary>Wijzigingsgeschiedenis ({changes.length})</summary>
        {changes.length === 0 ? (
          <p className="muted">Er zijn nog geen wijzigingen.</p>
        ) : (
          <ul className="history">
            {changes.map((c) => (
              <li key={c.id}>
                <strong>{formatDateTime(c.changed_at)}</strong> · {c.changed_by_name} ({roleNames[c.changed_by_role]})
                <br />
                {CARE_TYPES.find((t) => t.type === c.type)?.label}:{' '}
                {c.old_text === null
                  ? <>aangemaakt "{c.new_text}"</>
                  : <>"{c.old_text}" → "{c.new_text}"</>}
              </li>
            ))}
          </ul>
        )}
      </details>
    </section>
  )
}
