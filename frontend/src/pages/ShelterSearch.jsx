import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../api.js'
import { formatDate, speciesLabel, today } from '../constants.js'
import Alert from '../components/Alert.jsx'
import FormField from '../components/FormField.jsx'

// Scherm "Opvang zoeken" (FE2): dier + periode kiezen, beschikbare opvang zien en aanvragen
export default function ShelterSearch() {
  const navigate = useNavigate()

  const [pets, setPets] = useState([])
  const [form, setForm] = useState({ pet_id: '', start: today(), end: today() })
  const [errors, setErrors] = useState({})
  const [errorMessage, setErrorMessage] = useState('')
  const [results, setResults] = useState(null) // null = nog niet gezocht
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)

  // Dieren van de eigenaar ophalen voor de keuzelijst
  useEffect(() => {
    api('/pets')
      .then((data) => setPets(data.pets))
      .catch((error) => setErrorMessage(error.message))
      .finally(() => setLoading(false))
  }, [])

  const selectedPet = pets.find((p) => String(p.id) === form.pet_id)

  function handleChange(event) {
    setForm({ ...form, [event.target.name]: event.target.value })
    setResults(null) // andere invoer = oude resultaten kloppen niet meer
  }

  async function search() {
    setErrors({})
    setErrorMessage('')

    if (!selectedPet) {
      setErrors({ pet_id: 'Kies een dier.' })
      return
    }

    setBusy(true)
    try {
      const params = new URLSearchParams({ species: selectedPet.species, start: form.start, end: form.end })
      const data = await api(`/availability?${params}`)
      setResults(data.shelters)
    } catch (error) {
      setErrorMessage(error.message)
      setErrors(error.errors ?? {})
    } finally {
      setBusy(false)
    }
  }

  function handleSubmit(event) {
    event.preventDefault()
    search()
  }

  async function book(shelter) {
    setErrorMessage('')
    setBusy(true)
    try {
      const data = await api('/bookings', {
        method: 'POST',
        body: { pet_id: form.pet_id, shelter_id: shelter.id, start_date: form.start, end_date: form.end },
      })
      navigate('/mijn-aanvragen', { state: { message: data.message } })
    } catch (error) {
      // Bv. iemand anders was net eerder: melding tonen en resultaten verversen
      setErrorMessage(error.message)
      setBusy(false)
      search()
    }
  }

  if (loading) {
    return <p className="loading">Bezig met laden…</p>
  }

  // Zonder dieren kun je niet boeken
  if (pets.length === 0 && !errorMessage) {
    return (
      <section>
        <h1>Opvang zoeken</h1>
        <Alert type="empty">
          Voeg eerst een dier toe voordat je opvang zoekt.{' '}
          <Link to="/dieren/nieuw" className="btn btn-primary btn-small">+ Dier toevoegen</Link>
        </Alert>
      </section>
    )
  }

  return (
    <section>
      <h1>Opvang zoeken</h1>

      {errorMessage && <Alert type="error" onClose={() => setErrorMessage('')}>{errorMessage}</Alert>}

      <div className="search-layout">
        <form className="card" onSubmit={handleSubmit} noValidate>
          <FormField as="select" id="pet_id" name="pet_id" label="Dier *"
            value={form.pet_id} onChange={handleChange} error={errors.pet_id || errors.species}>
            <option value="">Kies een dier</option>
            {pets.map((p) => <option key={p.id} value={p.id}>{p.name} ({speciesLabel(p.species)})</option>)}
          </FormField>

          <FormField id="start" name="start" type="date" label="Van *" min={today()}
            value={form.start} onChange={handleChange} error={errors.start} />

          <FormField id="end" name="end" type="date" label="Tot en met *" min={form.start}
            value={form.end} onChange={handleChange} error={errors.end} />

          <button type="submit" className="btn btn-primary btn-full" disabled={busy}>
            {busy ? 'Bezig…' : 'Zoeken'}
          </button>
        </form>

        <div>
          {results !== null && results.length === 0 && (
            <Alert type="empty">
              Geen opvang gevonden die deze diersoort ({speciesLabel(selectedPet?.species).toLowerCase()}) aanneemt in deze periode.
            </Alert>
          )}

          {results?.map((shelter) => (
            <article key={shelter.id} className="card">
              <h2>{shelter.name}</h2>
              <p className="muted">{shelter.address}</p>

              {shelter.available ? (
                <p className="status-text ok">
                  ✓ {shelter.free_slots} {shelter.free_slots === 1 ? 'plek' : 'plekken'} vrij
                </p>
              ) : (
                <p className="status-text full">
                  ✕ Vol op {shelter.full_dates.map(formatDate).join(', ')}
                </p>
              )}

              {/* Knop uit als het vol is. PHP controleert het bij versturen toch opnieuw. */}
              <button type="button" className="btn btn-primary" disabled={!shelter.available || busy}
                onClick={() => book(shelter)}>
                Aanvraag versturen
              </button>
            </article>
          ))}
        </div>
      </div>
    </section>
  )
}
