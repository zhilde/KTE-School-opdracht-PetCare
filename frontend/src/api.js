// Alle communicatie met de PHP-API gaat via deze functie (TE4)

// Vaste meldingen voor technische fouten (ontwerp §9: geen technische details voor de gebruiker).
// Alert.jsx herkent deze en toont dan een knop "Opnieuw proberen".
export const CONNECTION_ERROR = 'Kan geen verbinding maken met de server. Probeer het opnieuw.'
export const TECHNICAL_ERROR = 'Er ging iets mis. Probeer het later opnieuw.'

// Fout van de API, met de statuscode en eventueel fouten per veld
export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

// Functie die wordt aangeroepen als de sessie verlopen is (ingesteld door AuthContext)
let onUnauthorized = null

export function setUnauthorizedHandler(handler) {
  onUnauthorized = handler
}

// Voorbeeld: await api('/login', { method: 'POST', body: { email, password } })
export async function api(path, { method = 'GET', body } = {}) {
  let response

  try {
    response = await fetch('/api' + path, {
      method,
      credentials: 'same-origin', // sessiecookie meesturen
      headers: body !== undefined ? { 'Content-Type': 'application/json' } : {},
      body: body !== undefined ? JSON.stringify(body) : undefined,
    })
  } catch {
    // Server niet bereikbaar (bv. PHP-server staat uit)
    throw new ApiError(CONNECTION_ERROR, 0)
  }

  // Als het antwoord geen geldige JSON is, wordt data null
  const data = await response.json().catch(() => null)

  // 401 tijdens het gebruik = sessie verlopen -> terug naar inloggen.
  // Niet bij /login (fout wachtwoord) en /me (controle bij opstarten).
  if (response.status === 401 && path !== '/login' && path !== '/me' && onUnauthorized) {
    onUnauthorized()
  }

  // Ook als het antwoord geen JSON is (bv. een PHP-foutpagina) tonen we een nette melding
  if (!response.ok || data === null) {
    // Bij een serverfout (500 of hoger) altijd de vaste melding, nooit technische details
    const message = response.status >= 500 || !data?.message ? TECHNICAL_ERROR : data.message
    throw new ApiError(message, response.status, data?.errors ?? {})
  }

  return data
}
