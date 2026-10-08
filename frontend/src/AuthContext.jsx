import { createContext, useContext, useEffect, useState } from 'react'
import { api } from './api.js'

// Houdt bij wie er is ingelogd, zodat elk scherm dat kan gebruiken met useAuth()
const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  // Bij het openen van de app vragen we aan PHP of er nog een sessie is
  useEffect(() => {
    api('/me')
      .then((data) => setUser(data.user))
      .catch(() => setUser(null))
      .finally(() => setLoading(false))
  }, [])

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
