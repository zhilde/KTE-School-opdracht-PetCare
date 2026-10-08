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
