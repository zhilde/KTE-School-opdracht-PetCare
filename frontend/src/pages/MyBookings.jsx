import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { api } from '../api.js'
import { formatDate } from '../constants.js'
import Alert from '../components/Alert.jsx'
import StatusBadge from '../components/StatusBadge.jsx'

// Scherm "Mijn aanvragen" (FE2, FE6): alle aanvragen van de eigenaar met hun status
export default function MyBookings() {
  const location = useLocation()
  const navigate = useNavigate()

  const [bookings, setBookings] = useState([])
  const [loading, setLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState('')
  // Succesmelding na het versturen van een aanvraag
  const [message, setMessage] = useState(location.state?.message ?? '')

  useEffect(() => {
    api('/bookings/mine')
      .then((data) => setBookings(data.bookings))
      .catch((error) => setErrorMessage(error.message))
      .finally(() => setLoading(false))
    // Melding uit de navigatie halen, zodat hij na verversen niet terugkomt
    navigate('.', { replace: true, state: null })
  }, [navigate])

  return (
    <section>
      <div className="page-head">
        <h1>Mijn aanvragen</h1>
        <Link to="/opvang-zoeken" className="btn btn-primary">+ Opvang zoeken</Link>
      </div>

      {message && <Alert type="success" onClose={() => setMessage('')}>{message}</Alert>}
      {errorMessage && <Alert type="error">{errorMessage}</Alert>}
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
          </article>
        ))}
      </div>
    </section>
  )
}
