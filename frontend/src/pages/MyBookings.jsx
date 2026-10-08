import { useCallback, useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { api } from '../api.js'
import { formatDate, today } from '../constants.js'
import Alert from '../components/Alert.jsx'
import FormField from '../components/FormField.jsx'
import StatusBadge from '../components/StatusBadge.jsx'

// Scherm "Mijn aanvragen" (FE2, FE6): alle aanvragen van de eigenaar met hun status.
// Een aanvraag die nog niet begonnen is kan de eigenaar annuleren of naar een andere opvang verplaatsen.
export default function MyBookings() {
  const location = useLocation()
  const navigate = useNavigate()

  const [bookings, setBookings] = useState([])
  const [loading, setLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState('')
  // Succesmelding na het versturen van een aanvraag
  const [message, setMessage] = useState(location.state?.message ?? '')

  // Welke aanvraag is open voor een actie, en welke actie: 'cancel' of 'move'
  const [activeId, setActiveId] = useState(null)
  const [action, setAction] = useState(null)
  // Voor verplaatsen: opvangen met plek, gekozen opvang en fout bij het veld
  const [options, setOptions] = useState(null)
  const [newShelterId, setNewShelterId] = useState('')
  const [fieldError, setFieldError] = useState('')
  const [busy, setBusy] = useState(false)

  const fetchBookings = useCallback(() => {
    return api('/bookings/mine')
      .then((data) => setBookings(data.bookings))
      .catch((error) => setErrorMessage(error.message))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => {
    fetchBookings()
    // Melding uit de navigatie halen, zodat hij na verversen niet terugkomt
    navigate('.', { replace: true, state: null })
  }, [fetchBookings, navigate])

  // Mag deze aanvraag nog gewijzigd worden? (zelfde regel als in de backend)
  function canChange(b) {
    return (b.status === 'PENDING' || b.status === 'APPROVED') && b.start_date >= today()
  }

  function close() {
    setActiveId(null)
    setAction(null)
    setOptions(null)
    setNewShelterId('')
    setFieldError('')
  }

  function startCancel(b) {
    close()
    setActiveId(b.id)
    setAction('cancel')
    setMessage('')
  }

  // Verplaatsen: eerst opvangen met plek ophalen voor dezelfde diersoort en periode
  async function startMove(b) {
    close()
    setActiveId(b.id)
    setAction('move')
    setMessage('')
    try {
      const params = new URLSearchParams({ species: b.species, start: b.start_date, end: b.end_date })
      const data = await api(`/availability?${params}`)
      // Alleen andere opvangen waar op elke dag plek is
      setOptions(data.shelters.filter((s) => s.id !== b.shelter_id && s.available))
    } catch (error) {
      setErrorMessage(error.message)
      close()
    }
  }

  async function confirmCancel(b) {
    setBusy(true)
    setErrorMessage('')
    try {
      const data = await api(`/bookings/${b.id}/cancel`, { method: 'PUT' })
      setMessage(data.message)
    } catch (error) {
      setErrorMessage(error.message)
    } finally {
      setBusy(false)
      close()
      fetchBookings()
    }
  }

  async function confirmMove(event, b) {
    event.preventDefault()
    setBusy(true)
    setErrorMessage('')
    setFieldError('')
    try {
      const data = await api(`/bookings/${b.id}/shelter`, { method: 'PUT', body: { shelter_id: newShelterId } })
      setMessage(data.message)
      close()
      fetchBookings()
    } catch (error) {
      // Veldfout (bv. niks gekozen) onder het veld, anders bovenaan (bv. net vol geraakt)
      if (error.errors?.shelter_id) {
        setFieldError(error.errors.shelter_id)
      } else {
        setErrorMessage(error.message)
        close()
        fetchBookings()
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <section>
      <div className="page-head">
        <h1>Mijn aanvragen</h1>
        <Link to="/opvang-zoeken" className="btn btn-primary">+ Opvang zoeken</Link>
      </div>

      {message && <Alert type="success" onClose={() => setMessage('')}>{message}</Alert>}
      {errorMessage && <Alert type="error" onClose={() => setErrorMessage('')}>{errorMessage}</Alert>}
      {loading && <p className="loading">Bezig met laden…</p>}

      {!loading && !errorMessage && bookings.length === 0 && (
        <Alert type="empty">Je hebt nog geen aanvragen gedaan.</Alert>
      )}

      <div className="grid">
        {bookings.map((b) => (
          <article key={b.id} className="card">
            <h2>{b.pet_name} · {b.shelter_name}</h2>
            <p className="muted">{formatDate(b.start_date)} t/m {formatDate(b.end_date)}</p>
            <StatusBadge status={b.status} />

            {/* Knoppen alleen als de aanvraag nog gewijzigd mag worden */}
            {canChange(b) && activeId !== b.id && (
              <div className="actions card-actions">
                <button type="button" className="btn btn-secondary btn-small" onClick={() => startMove(b)}>
                  Andere opvang
                </button>
                <button type="button" className="btn btn-danger btn-small" onClick={() => startCancel(b)}>
                  Annuleren
                </button>
              </div>
            )}

            {/* Annuleren: eerst bevestigen */}
            {activeId === b.id && action === 'cancel' && (
              <div className="card-actions" role="group" aria-label={`Annuleren van de aanvraag voor ${b.pet_name}`}>
                <p><strong>Weet je zeker dat je deze aanvraag wilt annuleren?</strong></p>
                <div className="actions">
                  <button type="button" className="btn btn-danger btn-small" disabled={busy}
                    onClick={() => confirmCancel(b)}>Ja, annuleren</button>
                  <button type="button" className="btn btn-secondary btn-small" onClick={close}>Nee</button>
                </div>
              </div>
            )}

            {/* Verplaatsen naar een andere opvang */}
            {activeId === b.id && action === 'move' && (
              <div className="card-actions">
                {options === null && <p className="loading">Beschikbare opvangen zoeken…</p>}

                {options?.length === 0 && (
                  <>
                    <Alert type="empty">Er is geen andere opvang met plek in deze periode.</Alert>
                    <button type="button" className="btn btn-secondary btn-small" onClick={close}>Sluiten</button>
                  </>
                )}

                {options?.length > 0 && (
                  <form onSubmit={(e) => confirmMove(e, b)} noValidate>
                    <FormField as="select" id={`shelter-${b.id}`} label="Nieuwe opvang *"
                      value={newShelterId} onChange={(e) => setNewShelterId(e.target.value)} error={fieldError}>
                      <option value="">Kies een opvang</option>
                      {options.map((s) => (
                        <option key={s.id} value={s.id}>{s.name} ({s.free_slots} vrij)</option>
                      ))}
                    </FormField>
                    <p className="muted">De nieuwe opvang moet je aanvraag opnieuw beoordelen.</p>
                    <div className="actions">
                      <button type="submit" className="btn btn-primary btn-small" disabled={busy}>
                        {busy ? 'Bezig…' : 'Verplaatsen'}
                      </button>
                      <button type="button" className="btn btn-secondary btn-small" onClick={close}>Annuleren</button>
                    </div>
                  </form>
                )}
              </div>
            )}
          </article>
        ))}
      </div>
    </section>
  )
}
