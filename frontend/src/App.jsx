import { Navigate, Route, Routes } from 'react-router-dom'
import { homePath, useAuth } from './AuthContext.jsx'
import Layout from './components/Layout.jsx'
import RequireAuth from './components/RequireAuth.jsx'
import Login from './pages/Login.jsx'
import PetList from './pages/PetList.jsx'
import PetForm from './pages/PetForm.jsx'
import Capacity from './pages/Capacity.jsx'
import Employees from './pages/Employees.jsx'
import ShelterSearch from './pages/ShelterSearch.jsx'
import MyBookings from './pages/MyBookings.jsx'
import Planning from './pages/Planning.jsx'
import PetDetail from './pages/PetDetail.jsx'
import Requests from './pages/Requests.jsx'
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

        <Route path="/dieren" element={<RequireAuth roles={OWNER}><PetList /></RequireAuth>} />
        <Route path="/dieren/nieuw" element={<RequireAuth roles={OWNER}><PetForm /></RequireAuth>} />
        {/* Dierdetails: ook voor medewerker/beheerder (alleen bekijken, FE7 bepaalt de API) */}
        <Route path="/dieren/:id" element={<RequireAuth><PetDetail /></RequireAuth>} />
        <Route path="/dieren/:id/bewerken" element={<RequireAuth roles={OWNER}><PetForm /></RequireAuth>} />
        <Route path="/opvang-zoeken" element={<RequireAuth roles={OWNER}><ShelterSearch /></RequireAuth>} />
        <Route path="/mijn-aanvragen" element={<RequireAuth roles={OWNER}><MyBookings /></RequireAuth>} />

        <Route path="/aanvragen" element={<RequireAuth roles={STAFF}><Requests /></RequireAuth>} />
        <Route path="/dagplanning" element={<RequireAuth roles={STAFF}><Planning /></RequireAuth>} />

        <Route path="/capaciteit" element={<RequireAuth roles={ADMIN}><Capacity /></RequireAuth>} />
        <Route path="/medewerkers" element={<RequireAuth roles={ADMIN}><Employees /></RequireAuth>} />

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
