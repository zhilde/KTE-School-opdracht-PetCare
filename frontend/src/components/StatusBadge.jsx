// Statuslabel van een aanvraag (FE6). Icoon + tekst + kleur, dus niet alleen kleur.
const statuses = {
  PENDING: { className: 'badge-pending', text: '⏳ In behandeling' },
  APPROVED: { className: 'badge-approved', text: '✓ Goedgekeurd' },
  REJECTED: { className: 'badge-rejected', text: '✕ Afgewezen' },
}

export default function StatusBadge({ status }) {
  const { className, text } = statuses[status] ?? { className: '', text: status }
  return <span className={`badge ${className}`}>{text}</span>
}
