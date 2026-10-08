import { Link } from 'react-router-dom'
import Alert from '../components/Alert.jsx'

export default function NotFound() {
  return (
    <section>
      <h1>Pagina niet gevonden</h1>
      <Alert type="error">Deze pagina bestaat niet.</Alert>
      <Link to="/">Terug naar de startpagina</Link>
    </section>
  )
}
