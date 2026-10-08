import { useCallback, useEffect, useState } from 'react'
import { api } from '../api.js'
import { formatDate, speciesLabel } from '../constants.js'
import Alert from '../components/Alert.jsx'
import FormField from '../components/FormField.jsx'
import StatusBadge from '../components/StatusBadge.jsx'

const STATUS_OPTIONS = [
  { value: 'PENDING', label: 'In behandeling' },
  { value: 'APPROVED', label: 'Goedgekeurd' },
  { value: 'REJECTED', label: 'Afgewezen' },
  { value: '', label: 'Alle statussen' },
]

// Scherm "Aanvragen" voor medewerker en beheerder (FE6):
// aanvragen van de eigen opvang(en) bekijken, goedkeuren of afwijzen
export default function Requests() {
  const [filters, setFilters] = useState({ status: 'PENDING', shelter_id: '' })
  const [shelters, setShelters] = useState([])
  const [bookings, setBookings] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState('')
  const [errorMessage, setErrorMessage] = useState('')
  // Id van de aanvraag waarbij we om bevestiging vragen voor afwijzen
  const [confirmRejectId, setConfirmRejectId] = useState(null)
  const [busyId, setBusyId] = useState(null)

  // Aanvragen ophalen met de huidige filters (lege filters niet meesturen)
  const fetchBookings = useCallback(() => {
    const params = new URLSearchParams()
    if (filters.status) params.append('status', filters.status)
    if (filters.shelter_id) params.append('shelter_id', filters.shelter_id)

    return api(`/bookings?${params}`)
      .then((data) => {
        setShelters(data.shelters)
        setBookings(data.bookings)
      })
      .catch((error) => setErrorMessage(error.message))
      .finally(() => setLoading(false))
  }, [filters])

  // Opnieuw ophalen als een filter verandert
  useEffect(() => {
    fetchBookings()
  }, [fetchBookings])

  function handleFilterChange(event) {
    setLoading(true)
    setFilters({ ...filters, [event.target.name]: event.target.value })
  }

  // action = 'approve' of 'reject'
  async function review(booking, action) {
    setMessage('')
    setErrorMessage('')
    setBusyId(booking.id)
    try {
      const data = await api(`/bookings/${booking.id}/${action}`, { method: 'PUT' })
      setMessage(data.message)
    } catch (error) {
      // Bv. "Deze aanvraag is al beoordeeld" (een collega was net eerder)
      setErrorMessage(error.message)
    } finally {
      setBusyId(null)
      setConfirmRejectId(null)
      fetchBookings() // lijst verversen: de aanvraag verdwijnt uit "In behandeling"
    }
  }

  return (
    <section>
      <h1>Aanvragen</h1>

      {message && <Alert type="success" onClose={() => setMessage('')}>{message}</Alert>}
      {errorMessage && <Alert type="error" onClose={() => setErrorMessage('')}>{errorMessage}</Alert>}

      <div className="card filters">
        <FormField as="select" id="status" name="status" label="Status"
          value={filters.status} onChange={handleFilterChange}>
          {STATUS_OPTIONS.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
        </FormField>

        <FormField as="select" id="shelter_id" name="shelter_id" label="Opvang"
          value={filters.shelter_id} onChange={handleFilterChange}>
          <option value="">Alle mijn opvangen</option>
          {shelters.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </FormField>
      </div>

      {loading && <p className="loading">Bezig met laden…</p>}

      {!loading && shelters.length === 0 && !errorMessage && (
        <Alert type="empty">Je bent nog niet aan een opvang gekoppeld. Vraag de beheerder om je te koppelen.</Alert>
      )}

      {!loading && shelters.length > 0 && bookings.length === 0 && !errorMessage && (
        <Alert type="empty">Geen aanvragen gevonden.</Alert>
      )}

      {bookings.length > 0 && (
        <div className="table-wrap">
          <table className="table">
            <thead>
              <tr><th>Dier</th><th>Soort</th><th>Eigenaar</th><th>Opvang</th><th>Periode</th><th>Status</th><th>Actie</th></tr>
            </thead>
            <tbody>
              {bookings.map((b) => (
                <tr key={b.id}>
                  <td data-label="Dier"><strong>{b.pet_name}</strong></td>
                  <td data-label="Soort">{speciesLabel(b.species)}</td>
                  <td data-label="Eigenaar">{b.owner_name}</td>
                  <td data-label="Opvang">{b.shelter_name}</td>
                  <td data-label="Periode">{formatDate(b.start_date)} t/m {formatDate(b.end_date)}</td>
                  <td data-label="Status"><StatusBadge status={b.status} /></td>
                  <td data-label="Actie">
                    {/* Alleen open aanvragen kun je beoordelen */}
                    {b.status !== 'PENDING' ? (
                      <span className="muted">–</span>
                    ) : confirmRejectId === b.id ? (
                      <div className="actions">
                        <span>Zeker afwijzen?</span>
                        <button type="button" className="btn btn-danger btn-small" disabled={busyId === b.id}
                          onClick={() => review(b, 'reject')}>Ja, afwijzen</button>
                        <button type="button" className="btn btn-secondary btn-small"
                          onClick={() => setConfirmRejectId(null)}>Annuleren</button>
                      </div>
                    ) : (
                      <div className="actions">
                        <button type="button" className="btn btn-primary btn-small" disabled={busyId === b.id}
                          onClick={() => review(b, 'approve')}>Goedkeuren</button>
                        <button type="button" className="btn btn-danger btn-small" disabled={busyId === b.id}
                          onClick={() => setConfirmRejectId(b.id)}>Afwijzen</button>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
