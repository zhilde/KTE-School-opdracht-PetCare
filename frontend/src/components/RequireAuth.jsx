import { Navigate } from 'react-router-dom'
import { useAuth } from '../AuthContext.jsx'
import Alert from './Alert.jsx'

// Laat een pagina alleen zien als je bent ingelogd en de juiste rol hebt.
// Let op: dit is alleen voor de gebruiker, de echte beveiliging zit in PHP.
export default function RequireAuth({ roles, children }) {
  const { user, loading } = useAuth()

  if (loading) {
    return <p className="loading">Bezig met laden…</p>
  }

  if (!user) {
    return <Navigate to="/login" replace />
  }

  if (roles && !roles.includes(user.role)) {
    return <Alert type="error">Je hebt geen toegang tot deze pagina.</Alert>
  }

  return children
}
