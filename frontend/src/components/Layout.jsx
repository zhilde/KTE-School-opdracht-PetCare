import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../AuthContext.jsx'
import logo from '../assets/logo.png'

// Menu per rol. Dit is alleen gemak voor de gebruiker, PHP controleert de rechten zelf ook.
const menu = {
  owner: [
    { to: '/dieren', label: 'Mijn dieren' },
    { to: '/opvang-zoeken', label: 'Opvang zoeken' },
    { to: '/mijn-aanvragen', label: 'Mijn aanvragen' },
  ],
  employee: [
    { to: '/aanvragen', label: 'Aanvragen' },
    { to: '/dagplanning', label: 'Dagplanning' },
  ],
  admin: [
    { to: '/aanvragen', label: 'Aanvragen' },
    { to: '/dagplanning', label: 'Dagplanning' },
    { to: '/capaciteit', label: 'Capaciteit' },
    { to: '/medewerkers', label: 'Medewerkers' },
  ],
}

// Kopbalk met logo en menu, daaronder het scherm van de huidige pagina (Outlet)
export default function Layout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [menuOpen, setMenuOpen] = useState(false)

  async function handleLogout() {
    try {
      await logout()
      navigate('/login', { state: { message: 'Je bent uitgelogd.' } })
    } catch {
      navigate('/login')
    }
  }

  return (
    <>
      <header className="topbar">
        <img src={logo} alt="PetCare" className="topbar-logo" />
        <button
          type="button"
          className="menu-toggle"
          aria-label={menuOpen ? 'Menu sluiten' : 'Menu openen'}
          aria-expanded={menuOpen}
          onClick={() => setMenuOpen(!menuOpen)}
        >
          ☰
        </button>
        <nav className={menuOpen ? 'mainnav open' : 'mainnav'} aria-label="Hoofdmenu">
          {menu[user.role].map((item) => (
            <NavLink key={item.to} to={item.to} onClick={() => setMenuOpen(false)}>
              {item.label}
            </NavLink>
          ))}
          <span className="user">
            {user.name} ·{' '}
            <button type="button" className="link-button" onClick={handleLogout}>
              Uitloggen
            </button>
          </span>
        </nav>
      </header>

      <main className="container">
        <Outlet />
      </main>
    </>
  )
}
