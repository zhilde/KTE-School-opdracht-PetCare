import { useEffect, useState } from 'react'
import { api } from '../api.js'
import Alert from '../components/Alert.jsx'
import FormField from '../components/FormField.jsx'

// Scherm "Medewerkers" voor de beheerder (AFW-2):
// medewerkers koppelen aan een opvang en weer ontkoppelen
export default function Employees() {
  const [employees, setEmployees] = useState([])
  const [shelters, setShelters] = useState([])
  const [links, setLinks] = useState([])
  const [form, setForm] = useState({ user_id: '', shelter_id: '' })
  const [errors, setErrors] = useState({})
  const [message, setMessage] = useState('')
  const [errorMessage, setErrorMessage] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)

  function fetchLinks() {
    return api('/shelter-employees').then((data) => setLinks(data.links))
  }

  // Alles tegelijk ophalen bij het openen van het scherm
  useEffect(() => {
    Promise.all([api('/employees'), api('/shelters'), fetchLinks()])
      .then(([employeeData, shelterData]) => {
        setEmployees(employeeData.employees)
        setShelters(shelterData.shelters)
      })
      .catch((error) => setErrorMessage(error.message))
      .finally(() => setLoading(false))
  }, [])

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
      const data = await api('/shelter-employees', { method: 'POST', body: form })
      setMessage(data.message)
      await fetchLinks()
    } catch (error) {
      setErrorMessage(error.message)
      setErrors(error.errors ?? {})
    } finally {
      setBusy(false)
    }
  }

  async function handleUnlink(link) {
    setMessage('')
    setErrorMessage('')
    try {
      const data = await api(`/shelter-employees/${link.id}`, { method: 'DELETE' })
      setMessage(data.message)
      setLinks(links.filter((l) => l.id !== link.id))
    } catch (error) {
      setErrorMessage(error.message)
    }
  }

  if (loading) {
    return <p className="loading">Bezig met laden…</p>
  }

  return (
    <section>
      <h1>Medewerkers</h1>
      <p className="muted">Een medewerker ziet alleen de aanvragen en dieren van de opvang(en) waaraan hij gekoppeld is.</p>

      {message && <Alert type="success" onClose={() => setMessage('')}>{message}</Alert>}
      {errorMessage && <Alert type="error" onClose={() => setErrorMessage('')}>{errorMessage}</Alert>}

      <form className="card filters" onSubmit={handleSubmit} noValidate>
        <FormField as="select" id="user_id" name="user_id" label="Medewerker *"
          value={form.user_id} onChange={handleChange} error={errors.user_id}>
          <option value="">Kies een medewerker</option>
          {employees.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
        </FormField>

        <FormField as="select" id="shelter_id" name="shelter_id" label="Opvang *"
          value={form.shelter_id} onChange={handleChange} error={errors.shelter_id}>
          <option value="">Kies een opvang</option>
          {shelters.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </FormField>

        <button type="submit" className="btn btn-primary" disabled={busy}>
          {busy ? 'Bezig…' : 'Koppelen'}
        </button>
      </form>

      <h2>Koppelingen</h2>

      {links.length === 0 ? (
        <Alert type="empty">Er zijn nog geen medewerkers gekoppeld.</Alert>
      ) : (
        <ul className="card list">
          {links.map((link) => (
            <li key={link.id}>
              <span><strong>{link.employee_name}</strong> · {link.shelter_name}</span>
              <button type="button" className="btn btn-danger btn-small" onClick={() => handleUnlink(link)}>
                Ontkoppelen
              </button>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
