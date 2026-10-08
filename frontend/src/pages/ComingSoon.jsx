import Alert from '../components/Alert.jsx'

// Tijdelijke pagina voor schermen die nog gebouwd moeten worden
export default function ComingSoon({ title, task }) {
  return (
    <section>
      <h1>{title}</h1>
      <Alert type="empty">Dit scherm wordt gebouwd in planningstaak {task}.</Alert>
    </section>
  )
}
