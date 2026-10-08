import { createContext, useContext, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, setUnauthorizedHandler } from './api.js'

// Houdt bij wie er is ingelogd, zodat elk scherm dat kan gebruiken met useAuth()
const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)
  const navigate = useNavigate()

  // Bij het openen van de app vragen we aan PHP of er nog een sessie is
  useEffect(() => {
    api('/me')
      .then((data) => setUser(data.user))
      .catch(() => setUser(null))
      .finally(() => setLoading(false))
  }, [])

  // Sessie verlopen tijdens het gebruik (API geeft 401)? Dan uitloggen en naar het loginscherm met een melding.
  useEffect(() => {
    setUnauthorizedHandler(() => {
      setUser(null)
      navigate('/login', {
        replace: true,
        state: { message: 'Je sessie is verlopen. Log opnieuw in.', type: 'error' },
      })
    })
  }, [navigate])

  async function login(email, password) {
    const data = await api('/login', { method: 'POST', body: { email, password } })
    setUser(data.user)
    return data.user
  }

  async function logout() {
    await api('/logout', { method: 'POST', body: {} })
    setUser(null)
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}

// Startpagina per rol, gebruikt na het inloggen
export function homePath(user) {
  return user.role === 'owner' ? '/dieren' : '/aanvragen'
}
