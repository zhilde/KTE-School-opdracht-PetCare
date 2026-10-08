import { Navigate, Route, Routes } from 'react-router-dom'
import { homePath, useAuth } from './AuthContext.jsx'
import Layout from './components/Layout.jsx'
import RequireAuth from './components/RequireAuth.jsx'
import Login from './pages/Login.jsx'
import ComingSoon from './pages/ComingSoon.jsx'
import NotFound from './pages/NotFound.jsx'

// Rollen, zodat ze overal hetzelfde geschreven worden
const OWNER = ['owner']
const STAFF = ['employee', 'admin']
const ADMIN = ['admin']

// Overzicht van alle schermen (ontwerp §5)
export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />

      {/* Alles hieronder alleen als je bent ingelogd, met kopbalk en menu */}
      <Route element={<RequireAuth><Layout /></RequireAuth>}>
        <Route path="/" element={<Home />} />

        <Route path="/dieren" element={<RequireAuth roles={OWNER}><ComingSoon title="Mijn dieren" task={6} /></RequireAuth>} />
        <Route path="/opvang-zoeken" element={<RequireAuth roles={OWNER}><ComingSoon title="Opvang zoeken" task={8} /></RequireAuth>} />
        <Route path="/mijn-aanvragen" element={<RequireAuth roles={OWNER}><ComingSoon title="Mijn aanvragen" task={8} /></RequireAuth>} />

        <Route path="/aanvragen" element={<RequireAuth roles={STAFF}><ComingSoon title="Aanvragen" task={11} /></RequireAuth>} />
        <Route path="/dagplanning" element={<RequireAuth roles={STAFF}><ComingSoon title="Dagplanning" task={9} /></RequireAuth>} />

        <Route path="/capaciteit" element={<RequireAuth roles={ADMIN}><ComingSoon title="Capaciteit" task={7} /></RequireAuth>} />
        <Route path="/medewerkers" element={<RequireAuth roles={ADMIN}><ComingSoon title="Medewerkers" task={7} /></RequireAuth>} />

        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  )
}

// "/" stuurt je door naar de startpagina van je rol
function Home() {
  const { user } = useAuth()
  return <Navigate to={homePath(user)} replace />
}
