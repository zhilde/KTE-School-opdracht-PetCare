import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { api } from '../api.js'
import { speciesLabel } from '../constants.js'
import Alert from '../components/Alert.jsx'

// Scherm "Mijn dieren" (FE1): overzicht, wijzigen en verwijderen
export default function PetList() {
  const location = useLocation()
  const navigate = useNavigate()

  const [pets, setPets] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  // Succesmelding kan meekomen van het formulier (na opslaan)
  const [message, setMessage] = useState(location.state?.message ?? '')
  const [errorMessage, setErrorMessage] = useState('')
  // Id van het dier waarbij we om bevestiging vragen voor verwijderen
  const [confirmId, setConfirmId] = useState(null)

  // Dieren ophalen bij de API
  function fetchPets() {
    api('/pets')
      .then((data) => setPets(data.pets))
      .catch((error) => setLoadError(error.message))
      .finally(() => setLoading(false))
  }

  // Knop "Opnieuw proberen" na een fout
  function retry() {
    setLoading(true)
    setLoadError('')
    fetchPets()
  }

  // Bij het openen van het scherm
  useEffect(() => {
    fetchPets()
    // Melding uit de navigatie halen, zodat hij na verversen niet terugkomt
    navigate('.', { replace: true, state: null })
  }, [navigate])

  async function handleDelete(pet) {
    setMessage('')
    setErrorMessage('')
    try {
      const data = await api(`/pets/${pet.id}`, { method: 'DELETE' })
      setMessage(data.message)
      setPets(pets.filter((p) => p.id !== pet.id))
    } catch (error) {
      setErrorMessage(error.message)
    } finally {
      setConfirmId(null)
    }
  }

  return (
    <section>
      <div className="page-head">
        <h1>Mijn dieren</h1>
        <Link to="/dieren/nieuw" className="btn btn-primary">+ Dier toevoegen</Link>
      </div>

      {message && <Alert type="success" onClose={() => setMessage('')}>{message}</Alert>}
      {errorMessage && <Alert type="error" onClose={() => setErrorMessage('')}>{errorMessage}</Alert>}

      {loading && <p className="loading">Bezig met laden…</p>}

      {loadError && (
        <Alert type="error">
          {loadError}{' '}
          <button type="button" className="btn btn-secondary btn-small" onClick={retry}>Opnieuw proberen</button>
        </Alert>
      )}

      {/* Leegmelding (FE8) */}
      {!loading && !loadError && pets.length === 0 && (
        <Alert type="empty">
          Je hebt nog geen dieren toegevoegd.{' '}
          <Link to="/dieren/nieuw" className="btn btn-primary btn-small">+ Dier toevoegen</Link>
        </Alert>
      )}

      <div className="grid">
        {pets.map((pet) => (
          <article key={pet.id} className="card">
            <h2>{pet.name}</h2>
            <p className="muted">{speciesLabel(pet.species)}</p>

            {confirmId === pet.id ? (
              // Eerst bevestigen, zodat je niet per ongeluk verwijdert
              <div role="group" aria-label={`Bevestig verwijderen van ${pet.name}`}>
                <p><strong>Weet je zeker dat je {pet.name} wilt verwijderen?</strong></p>
                <div className="actions">
                  <button type="button" className="btn btn-danger" onClick={() => handleDelete(pet)}>Ja, verwijderen</button>
                  <button type="button" className="btn btn-secondary" onClick={() => setConfirmId(null)}>Annuleren</button>
                </div>
              </div>
            ) : (
              <div className="actions">
                <Link to={`/dieren/${pet.id}/bewerken`} className="btn btn-secondary">Wijzigen</Link>
                <button type="button" className="btn btn-danger" onClick={() => setConfirmId(pet.id)}>Verwijderen</button>
              </div>
            )}
          </article>
        ))}
      </div>
    </section>
  )
}
