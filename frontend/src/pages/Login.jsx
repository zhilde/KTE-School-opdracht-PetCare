import { useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { homePath, useAuth } from '../AuthContext.jsx'
import Alert from '../components/Alert.jsx'
import FormField from '../components/FormField.jsx'
import logo from '../assets/logo.png'

export default function Login() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState({})
  // Melding die meekomt via de navigatie, bv. "Je bent uitgelogd." (succes)
  // of "Je sessie is verlopen." (fout)
  const [message, setMessage] = useState(location.state?.message ?? '')
  const messageType = location.state?.type ?? 'success'
  const [errorMessage, setErrorMessage] = useState('')
  const [busy, setBusy] = useState(false)

  // Al ingelogd? Dan meteen naar de startpagina
  if (user) {
    return <Navigate to={homePath(user)} replace />
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setErrorMessage('')
    setMessage('')
    setBusy(true)

    try {
      const loggedInUser = await login(email, password)
      navigate(homePath(loggedInUser))
    } catch (error) {
      setErrorMessage(error.message)
      setErrors(error.errors ?? {})
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="screen-login">
      <div className="card login-card">
        <img src={logo} alt="PetCare" className="login-logo" />

        {message && <Alert type={messageType}>{message}</Alert>}
        {errorMessage && <Alert type="error">{errorMessage}</Alert>}

        {/* noValidate: de controle doet PHP, zodat de meldingen overal hetzelfde zijn */}
        <form onSubmit={handleSubmit} noValidate>
          <FormField
            id="email"
            label="E-mailadres"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            error={errors.email}
          />
          <FormField
            id="password"
            label="Wachtwoord"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            error={errors.password}
          />
          <button type="submit" className="btn btn-primary btn-full" disabled={busy}>
            {busy ? 'Bezig met inloggen…' : 'Inloggen'}
          </button>
        </form>
      </div>
    </div>
  )
}
