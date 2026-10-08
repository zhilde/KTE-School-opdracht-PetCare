import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../api.js'
import { SPECIES } from '../constants.js'
import Alert from '../components/Alert.jsx'
import FormField from '../components/FormField.jsx'

// Formulier voor dier toevoegen én wijzigen (FE1).
// Met een id in de URL (/dieren/5/bewerken) is het wijzigen, anders toevoegen.
export default function PetForm() {
  const { id } = useParams()
  const isEdit = id !== undefined
  const navigate = useNavigate()

  const [name, setName] = useState('')
  const [species, setSpecies] = useState('')
  const [errors, setErrors] = useState({})
  const [errorMessage, setErrorMessage] = useState('')
  const [loading, setLoading] = useState(isEdit)
  const [busy, setBusy] = useState(false)

  // Bij wijzigen eerst de huidige gegevens ophalen
  useEffect(() => {
    if (!isEdit) return
    api(`/pets/${id}`)
      .then((data) => {
        setName(data.pet.name)
        setSpecies(data.pet.species)
      })
      .catch((error) => setErrorMessage(error.message))
      .finally(() => setLoading(false))
  }, [id, isEdit])

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setErrorMessage('')
    setBusy(true)

    try {
      const data = isEdit
        ? await api(`/pets/${id}`, { method: 'PUT', body: { name, species } })
        : await api('/pets', { method: 'POST', body: { name, species } })

      // Terug naar het overzicht met een succesmelding
      navigate('/dieren', { state: { message: data.message } })
    } catch (error) {
      // Formulier blijft ingevuld, fouten komen onder de velden (ontwerp §9)
      setErrorMessage(error.message)
      setErrors(error.errors ?? {})
    } finally {
      setBusy(false)
    }
  }

  if (loading) {
    return <p className="loading">Bezig met laden…</p>
  }

  return (
    <section>
      <h1>{isEdit ? 'Dier wijzigen' : 'Dier toevoegen'}</h1>

      {errorMessage && <Alert type="error">{errorMessage}</Alert>}

      <form className="card form-narrow" onSubmit={handleSubmit} noValidate>
        <p className="muted">Velden met * zijn verplicht.</p>

        <FormField
          id="name"
          label="Naam *"
          value={name}
          maxLength={100}
          onChange={(e) => setName(e.target.value)}
          error={errors.name}
        />

        <FormField
          as="select"
          id="species"
          label="Diersoort *"
          value={species}
          onChange={(e) => setSpecies(e.target.value)}
          error={errors.species}
        >
          <option value="">Kies een soort</option>
          {SPECIES.map((s) => (
            <option key={s.value} value={s.value}>{s.label}</option>
          ))}
        </FormField>

        <div className="actions">
          <button type="submit" className="btn btn-primary" disabled={busy}>
            {busy ? 'Bezig met opslaan…' : 'Opslaan'}
          </button>
          <Link to="/dieren" className="btn btn-secondary">Annuleren</Link>
        </div>
      </form>
    </section>
  )
}
