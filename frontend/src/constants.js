// Vaste lijsten die op meerdere schermen worden gebruikt

// Diersoorten (zelfde als in de database en PetController::SPECIES)
export const SPECIES = [
  { value: 'hond', label: 'Hond' },
  { value: 'kat', label: 'Kat' },
  { value: 'konijn', label: 'Konijn' },
  { value: 'overig', label: 'Overig' },
]

// 'hond' -> 'Hond'
export function speciesLabel(value) {
  return SPECIES.find((s) => s.value === value)?.label ?? value
}

// '2026-10-08' -> '08-10-2026' (Nederlandse notatie)
export function formatDate(isoDate) {
  const [year, month, day] = isoDate.split('-')
  return `${day}-${month}-${year}`
}

// Datum van vandaag als '2026-10-08' (zo werkt <input type="date">)
export function today() {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}
