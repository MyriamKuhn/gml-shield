import { useState } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Button,
  CircularProgress,
  Stack,
  Typography,
} from '@mui/material'
import {
  downloadDeliveryDocument,
  getMissionDocuments,
  type DeliveryDocument,
} from '../../api/documents'

interface MissionDocumentsListProps {
  missionId: number
}

/**
 * Display downloadable delivery proofs to the owning dispatcher.
 *
 * The backend validates ownership for listing and downloading.
 */
export default function MissionDocumentsList({
  missionId,
}: MissionDocumentsListProps) {
  const [documents, setDocuments] = useState<DeliveryDocument[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [loaded, setLoaded] = useState(false)
  const [downloadingId, setDownloadingId] = useState<number | null>(null)

  async function handleLoadDocuments() {
    setLoading(true)
    setError('')

    try {
      const data = await getMissionDocuments(missionId)
      setDocuments(data)
      setLoaded(true)
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 401) {
        setError('Votre session a expiré. Veuillez vous reconnecter.')
      } else if (err instanceof AxiosError && err.response?.status === 403) {
        setError("Vous n'êtes pas autorisé à consulter ces preuves.")
      } else {
        setError('Impossible de charger les preuves de livraison.')
      }
    } finally {
      setLoading(false)
    }
  }

  async function handleDownload(documentId: number) {
    if (downloadingId !== null) return

    setDownloadingId(documentId)
    setError('')

    try {
      await downloadDeliveryDocument(missionId, documentId)
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 401) {
        setError('Votre session a expiré. Veuillez vous reconnecter.')
      } else if (err instanceof AxiosError && err.response?.status === 403) {
        setError("Vous n'êtes pas autorisé à télécharger cette preuve.")
      } else if (err instanceof AxiosError && err.response?.status === 404) {
        setError('Cette preuve de livraison est introuvable.')
      } else {
        setError('Impossible de télécharger le document.')
      }
    } finally {
      setDownloadingId(null)
    }
  }

  return (
    <Stack spacing={1.5}>
      <Typography variant="subtitle2">
        Documents de livraison
      </Typography>

      <Button
        variant="outlined"
        onClick={() => void handleLoadDocuments()}
        disabled={loading}
      >
        {loading ? 'Chargement...' : 'Afficher / actualiser les preuves'}
      </Button>

      {loading && <CircularProgress size={20} />}

      {loaded && documents.length === 0 && (
        <Typography variant="body2" color="text.secondary">
          Aucune preuve de livraison disponible.
        </Typography>
      )}

      {documents.map((document) => (
        <Stack
          key={document.id}
          direction="row"
          spacing={1}
          sx={{
            alignItems: 'center',
            justifyContent: 'space-between',
          }}
        >
          <Typography variant="body2">
            Preuve #{document.id} — {document.mime_type}
          </Typography>

          <Button
            size="small"
            variant="outlined"
            disabled={downloadingId !== null}
            onClick={() => void handleDownload(document.id)}
          >
            {downloadingId === document.id ? 'Téléchargement...' : 'Télécharger'}
          </Button>
        </Stack>
      ))}

      {error && <Alert severity="error">{error}</Alert>}
    </Stack>
  )
}
