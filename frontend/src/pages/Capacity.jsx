import { useEffect, useState } from 'react'
import { api } from '../api.js'
import { SPECIES, formatDate, speciesLabel, today } from '../constants.js'
import Alert from '../components/Alert.jsx'
import FormField from '../components/FormField.jsx'

const MONTHS = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli',
  'augustus', 'september', 'oktober', 'november', 'december']
const WEEKDAYS = ['ma', 'di', 'wo', 'do', 'vr', 'za', 'zo']

// Date-object -> '2026-10-08'
function toIso(date) {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

// Alle dagen van een maand als lijst van Date-objecten
function daysOfMonth(year, month) {
  const days = []
  const date = new Date(year, month, 1)
  while (date.getMonth() === month) {
    days.push(new Date(date))
    date.setDate(date.getDate() + 1)
  }
  return days
}

// Scherm "Capaciteit" voor de beheerder (FE3):
// plekken instellen per opvang, datum en diersoort + maandagenda met bezet/vrij per dag
export default function Capacity() {
  const [shelters, setShelters] = useState([])
  const [form, setForm] = useState({ shelter_id: '', date: today(), species: '', max_slots: '' })
  const [errors, setErrors] = useState({})
  const [message, setMessage] = useState('')
  const [errorMessage, setErrorMessage] = useState('')
  const [busy, setBusy] = useState(false)

  // Agenda: welke maand wordt getoond (begint bij de huidige maand).
  // today() = '2026-10-08' -> jaar 2026, maand 9 (januari = 0 in JavaScript)
  const [currentYear, currentMonth] = [Number(today().slice(0, 4)), Number(today().slice(5, 7)) - 1]
  const [year, setYear] = useState(currentYear)
  const [month, setMonth] = useState(currentMonth)
  const [rows, setRows] = useState([])
  const [loadingRows, setLoadingRows] = useState(true)

  const days = daysOfMonth(year, month)
  const from = toIso(days[0])
  const to = toIso(days[days.length - 1])
  const isCurrentMonth = year === currentYear && month === currentMonth

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

  // Capaciteit van de hele maand ophalen
  function fetchRows(shelterId, fromDate, toDate) {
    api(`/capacities?shelter_id=${shelterId}&from=${fromDate}&to=${toDate}`)
      .then((data) => setRows(data.capacities))
      .catch((error) => setErrorMessage(error.message))
      .finally(() => setLoadingRows(false))
  }

  // Opnieuw laden als de opvang of de maand verandert
  useEffect(() => {
    if (form.shelter_id) {
      fetchRows(form.shelter_id, from, to)
    }
  }, [form.shelter_id, from, to])

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
      fetchRows(form.shelter_id, from, to)
    } catch (error) {
      setErrorMessage(error.message)
      setErrors(error.errors ?? {})
    } finally {
      setBusy(false)
    }
  }

  // Naar de vorige of volgende maand (step = -1 of 1)
  function changeMonth(step) {
    const date = new Date(year, month + step, 1)
    setYear(date.getFullYear())
    setMonth(date.getMonth())
    setLoadingRows(true)
  }

  // Klik op een dag of een diersoort in de agenda: gegevens in het formulier zetten
  function pick(date, row) {
    setForm({
      ...form,
      date,
      species: row ? row.species : form.species,
      max_slots: row ? String(row.max_slots) : form.max_slots,
    })
    setMessage('')
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  // Regels per datum groeperen: { '2026-10-08': [ {species: 'hond', ...}, ... ] }
  const rowsPerDate = {}
  for (const row of rows) {
    if (!rowsPerDate[row.date]) rowsPerDate[row.date] = []
    rowsPerDate[row.date].push(row)
  }

  // Lege vakjes vóór de 1e van de maand, zodat die op de goede weekdag staat (maandag = eerste kolom)
  const emptyCells = (days[0].getDay() + 6) % 7

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

      {/* Maandagenda */}
      <div className="calendar-head">
        <button type="button" className="btn btn-secondary btn-small" onClick={() => changeMonth(-1)}
          disabled={isCurrentMonth} aria-label="Vorige maand">←</button>
        <h2>{MONTHS[month]} {year} · {shelterName}</h2>
        <button type="button" className="btn btn-secondary btn-small" onClick={() => changeMonth(1)}
          aria-label="Volgende maand">→</button>
      </div>
      <p className="muted">Klik op een dag of diersoort om die in het formulier te zetten.</p>

      {loadingRows && <p className="loading">Bezig met laden…</p>}

      {!loadingRows && rows.length === 0 && (
        <Alert type="empty">Er is in {MONTHS[month]} nog geen capaciteit ingesteld voor deze opvang.</Alert>
      )}

      {!loadingRows && (
        <div className="calendar">
          {WEEKDAYS.map((d) => <div key={d} className="calendar-weekday">{d}</div>)}
          {Array.from({ length: emptyCells }, (_, i) => <div key={`leeg-${i}`} className="calendar-empty" />)}

          {days.map((day) => {
            const date = toIso(day)
            const isPast = date < today()
            const dayRows = rowsPerDate[date] ?? []
            // Op telefoon tonen we geen dagen zonder capaciteit, anders wordt de lijst erg lang
            const classes = ['calendar-day']
            if (isPast) classes.push('is-past')
            if (date === today()) classes.push('is-today')
            if (date === form.date) classes.push('is-selected')
            if (dayRows.length === 0) classes.push('is-empty')

            return (
              <div key={date} className={classes.join(' ')}>
                <button type="button" className="calendar-date" disabled={isPast} onClick={() => pick(date)}
                  aria-label={`${formatDate(date)} kiezen`}>
                  <span className="calendar-weekday-small">{WEEKDAYS[(day.getDay() + 6) % 7]}</span> {day.getDate()}
                </button>

                {dayRows.map((row) => (
                  <button key={row.species} type="button" disabled={isPast} onClick={() => pick(date, row)}
                    className={row.free === 0 ? 'calendar-slot is-full' : 'calendar-slot'}>
                    {speciesLabel(row.species)}{' '}
                    {row.free === 0
                      ? <>✕ vol ({row.booked}/{row.max_slots})</>
                      : <>{row.free}/{row.max_slots} vrij</>}
                  </button>
                ))}
              </div>
            )
          })}
        </div>
      )}
    </section>
  )
}
