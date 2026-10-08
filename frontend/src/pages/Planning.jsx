import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api.js'
import { SPECIES, formatDate, speciesLabel, today } from '../constants.js'
import Alert from '../components/Alert.jsx'
import FormField from '../components/FormField.jsx'

// Scherm "Dagplanning" voor medewerker en beheerder (FE4):
// welke dieren zijn op een dag aanwezig, filter op locatie en dienst (diersoort)
export default function Planning() {
  const [filters, setFilters] = useState({ date: today(), shelter_id: '', species: '' })
  const [shelters, setShelters] = useState([])
  const [items, setItems] = useState([])
  const [errors, setErrors] = useState({})
  const [errorMessage, setErrorMessage] = useState('')
  const [loading, setLoading] = useState(true)

  // Planning ophalen met de gekozen filters (lege filters niet meesturen)
  function fetchPlanning(currentFilters) {
    const params = new URLSearchParams({ date: currentFilters.date })
    if (currentFilters.shelter_id) params.append('shelter_id', currentFilters.shelter_id)
    if (currentFilters.species) params.append('species', currentFilters.species)

    api(`/planning?${params}`)
      .then((data) => {
        setShelters(data.shelters)
        setItems(data.items)
      })
      .catch((error) => {
        setErrorMessage(error.message)
        setErrors(error.errors ?? {})
        setItems([])
      })
      .finally(() => setLoading(false))
  }

  // Bij het openen: planning van vandaag
  useEffect(() => {
    fetchPlanning({ date: today(), shelter_id: '', species: '' })
  }, [])

  function handleChange(event) {
    setFilters({ ...filters, [event.target.name]: event.target.value })
  }

  function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setErrorMessage('')
    setLoading(true)
    fetchPlanning(filters)
  }

  // Dieren groeperen per opvang: { 'Dierenopvang Noord': [...], 'Opvang Zuid': [...] }
  const perShelter = {}
  for (const item of items) {
    if (!perShelter[item.shelter_name]) perShelter[item.shelter_name] = []
    perShelter[item.shelter_name].push(item)
  }

  return (
    <section>
      <h1>Dagplanning</h1>

      {errorMessage && <Alert type="error" onClose={() => setErrorMessage('')}>{errorMessage}</Alert>}

      <form className="card filters" onSubmit={handleSubmit} noValidate>
        <FormField id="date" name="date" type="date" label="Datum"
          value={filters.date} onChange={handleChange} error={errors.date} />

        <FormField as="select" id="shelter_id" name="shelter_id" label="Locatie"
          value={filters.shelter_id} onChange={handleChange} error={errors.shelter_id}>
          <option value="">Alle locaties</option>
          {shelters.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </FormField>

        <FormField as="select" id="species" name="species" label="Dienst (diersoort)"
          value={filters.species} onChange={handleChange} error={errors.species}>
          <option value="">Alle diersoorten</option>
          {SPECIES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
        </FormField>

        <button type="submit" className="btn btn-primary">Filteren</button>
      </form>

      {loading && <p className="loading">Bezig met laden…</p>}

      {/* Medewerker die nergens aan gekoppeld is */}
      {!loading && shelters.length === 0 && !errorMessage && (
        <Alert type="empty">Je bent nog niet aan een opvang gekoppeld. Vraag de beheerder om je te koppelen.</Alert>
      )}

      {/* Leegmelding bij filters zonder resultaat (FE8) */}
      {!loading && shelters.length > 0 && items.length === 0 && !errorMessage && (
        <Alert type="empty">Geen planning gevonden voor deze filters.</Alert>
      )}

      {Object.entries(perShelter).map(([shelterName, shelterItems]) => (
        <div key={shelterName}>
          <h2>{shelterName} – {shelterItems.length} {shelterItems.length === 1 ? 'dier' : 'dieren'}</h2>
          <ul className="card list">
            {shelterItems.map((item) => (
              <li key={item.booking_id}>
                <span>
                  <strong>{item.pet_name}</strong> · {speciesLabel(item.species)} · eigenaar {item.owner_name}
                </span>
                <span>
                  <span className="muted">t/m {formatDate(item.end_date)}</span>{' '}
                  <Link to={`/dieren/${item.pet_id}`}>Details →</Link>
                </span>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </section>
  )
}
