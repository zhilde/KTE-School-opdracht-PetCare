import { useEffect, useState } from 'react'
import { api } from '../api.js'
import { SPECIES, formatDate, speciesLabel, today } from '../constants.js'
import Alert from '../components/Alert.jsx'
import FormField from '../components/FormField.jsx'

// Scherm "Capaciteit" voor de beheerder (FE3):
// plekken instellen per opvang, datum en diersoort + overzicht van 14 dagen
export default function Capacity() {
  const [shelters, setShelters] = useState([])
  const [form, setForm] = useState({ shelter_id: '', date: today(), species: '', max_slots: '' })
  const [errors, setErrors] = useState({})
  const [message, setMessage] = useState('')
  const [errorMessage, setErrorMessage] = useState('')
  const [busy, setBusy] = useState(false)

  // Overzicht
  const [from, setFrom] = useState(today())
  const [rows, setRows] = useState([])
  const [loadingRows, setLoadingRows] = useState(true)

  // Opvangen ophalen voor de keuzelijst, de eerste opvang meteen kiezen
  useEffect(() => {
    api('/shelters')
      .then((data) => {
        setShelters(data.shelters)
        if (data.shelters.length > 0) {
          setForm((f) => ({ ...f, shelter_id: String(data.shelters[0].id) }))
        }
      })
      .catch((error) => setErrorMessage(error.message))
  }, [])

  // Overzicht ophalen voor de gekozen opvang en startdatum
  function fetchRows(shelterId, fromDate) {
    api(`/capacities?shelter_id=${shelterId}&from=${fromDate}`)
      .then((data) => setRows(data.capacities))
      .catch((error) => setErrorMessage(error.message))
      .finally(() => setLoadingRows(false))
  }

  // Opnieuw laden als de opvang of de startdatum verandert
  useEffect(() => {
    if (form.shelter_id && from) {
      fetchRows(form.shelter_id, from)
    }
  }, [form.shelter_id, from])

  // Eén functie voor alle velden: name van het veld = sleutel in form
  function handleChange(event) {
    setForm({ ...form, [event.target.name]: event.target.value })
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setMessage('')
    setErrorMessage('')
    setBusy(true)

    try {
      const data = await api('/capacities', { method: 'PUT', body: form })
      setMessage(data.message)
      setLoadingRows(true)
      fetchRows(form.shelter_id, from)
    } catch (error) {
      setErrorMessage(error.message)
      setErrors(error.errors ?? {})
    } finally {
      setBusy(false)
    }
  }

  // Knop "Wijzigen" in de tabel: gegevens van die regel in het formulier zetten
  function editRow(row) {
    setForm({ ...form, date: row.date, species: row.species, max_slots: String(row.max_slots) })
    setMessage('')
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const shelterName = shelters.find((s) => String(s.id) === form.shelter_id)?.name ?? ''

  return (
    <section>
      <h1>Capaciteit</h1>

      {message && <Alert type="success" onClose={() => setMessage('')}>{message}</Alert>}
      {errorMessage && <Alert type="error" onClose={() => setErrorMessage('')}>{errorMessage}</Alert>}

      <form className="card filters" onSubmit={handleSubmit} noValidate>
        <FormField as="select" id="shelter_id" name="shelter_id" label="Opvang *"
          value={form.shelter_id} onChange={handleChange} error={errors.shelter_id}>
          {shelters.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </FormField>

        <FormField id="date" name="date" type="date" label="Datum *" min={today()}
          value={form.date} onChange={handleChange} error={errors.date} />

        <FormField as="select" id="species" name="species" label="Diersoort *"
          value={form.species} onChange={handleChange} error={errors.species}>
          <option value="">Kies een soort</option>
          {SPECIES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
        </FormField>

        <FormField id="max_slots" name="max_slots" type="number" label="Plekken *" min="0" max="500"
          value={form.max_slots} onChange={handleChange} error={errors.max_slots} />

        <button type="submit" className="btn btn-primary" disabled={busy}>
          {busy ? 'Bezig met opslaan…' : 'Opslaan'}
        </button>
      </form>

      <div className="page-head">
        <h2>Overzicht {shelterName}</h2>
        <div className="field">
          <label htmlFor="from">Vanaf</label>
          <input id="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
        </div>
      </div>

      {loadingRows && <p className="loading">Bezig met laden…</p>}

      {!loadingRows && rows.length === 0 && (
        <Alert type="empty">Er is voor deze 14 dagen nog geen capaciteit ingesteld.</Alert>
      )}

      {rows.length > 0 && (
        <div className="table-wrap">
          <table className="table">
            <thead>
              <tr><th>Datum</th><th>Soort</th><th>Plekken</th><th>Bezet</th><th>Vrij</th><th>Actie</th></tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.date + row.species}>
                  <td data-label="Datum">{formatDate(row.date)}</td>
                  <td data-label="Soort">{speciesLabel(row.species)}</td>
                  <td data-label="Plekken">{row.max_slots}</td>
                  <td data-label="Bezet">{row.booked}</td>
                  <td data-label="Vrij">
                    {/* Vol: tekst + kleur, niet alleen kleur */}
                    {row.free === 0
                      ? <span className="status-text full">✕ Vol</span>
                      : row.free}
                  </td>
                  <td data-label="Actie">
                    <button type="button" className="btn btn-secondary btn-small" onClick={() => editRow(row)}>
                      Wijzigen
                    </button>
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
