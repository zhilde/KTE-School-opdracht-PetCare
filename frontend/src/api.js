// Alle communicatie met de PHP-API gaat via deze functie (TE4)

// Fout van de API, met de statuscode en eventueel fouten per veld
export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
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
    throw new ApiError('Kan geen verbinding maken met de server. Probeer het opnieuw.', 0)
  }

  // Als het antwoord geen geldige JSON is, wordt data null
  const data = await response.json().catch(() => null)

  // Ook als het antwoord geen JSON is (bv. een PHP-foutpagina) tonen we een nette melding
  if (!response.ok || data === null) {
    throw new ApiError(
      data?.message ?? 'Er ging iets mis. Probeer het later opnieuw.',
      response.status,
      data?.errors ?? {},
    )
  }

  return data
}
