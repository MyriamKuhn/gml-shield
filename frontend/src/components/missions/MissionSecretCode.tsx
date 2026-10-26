import { useEffect, useRef, useState } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Button,
  Stack,
  Typography,
} from '@mui/material'
import { revealMissionSecret } from '../../api/missions'

interface MissionSecretCodeProps {
  missionId: number
}

/**
 * Reveal the secret only on explicit request.
 *
 * Ignore obsolete API responses after hiding the secret
 * or unmounting the component.
 */
export default function MissionSecretCode({
  missionId,
}: MissionSecretCodeProps) {
  const [secretCode, setSecretCode] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  // Identify the latest authorized UI request.
  const requestId = useRef(0)

  /**
   * Invalidate pending responses when the component unmounts.
   * No synchronous state update is needed in this effect.
   */
  useEffect(() => {
    return () => {
      requestId.current += 1
    }
  }, [])

  async function handleReveal() {
    if (loading) return

    const currentRequestId = ++requestId.current

    setLoading(true)
    setError('')

    try {
      const secret = await revealMissionSecret(missionId)

      // Ignore responses from obsolete requests.
      if (currentRequestId !== requestId.current) return

      setSecretCode(secret)
    } catch (err) {
      if (currentRequestId !== requestId.current) return

      setSecretCode(null)

      if (err instanceof AxiosError) {
        if (err.response?.status === 401) {
          setError('Votre session a expiré.')
        } else if (err.response?.status === 403) {
          setError("Vous n'êtes pas autorisé à révéler ce code.")
        } else if (err.response?.status === 404) {
          setError('Aucun code secret disponible.')
        } else {
          setError('Impossible de récupérer le code secret.')
        }
      } else {
        setError('Une erreur inattendue est survenue.')
      }
    } finally {
      if (currentRequestId === requestId.current) {
        setLoading(false)
      }
    }
  }

  function handleHide() {
    // Invalidate any pending request before hiding.
    requestId.current += 1

    setSecretCode(null)
    setError('')
    setLoading(false)
  }

  return (
    <Stack spacing={1}>
      {error && <Alert severity="error">{error}</Alert>}

      {secretCode === null ? (
        <Button
          variant="outlined"
          loading={loading}
          disabled={loading}
          onClick={() => void handleReveal()}
        >
          Révéler le code secret
        </Button>
      ) : (
        <>
          <Typography
            variant="body2"
            sx={{ overflowWrap: 'anywhere' }}
          >
            Code d'accès : {secretCode}
          </Typography>

          <Button
            variant="text"
            onClick={handleHide}
          >
            Masquer le code
          </Button>
        </>
      )}
    </Stack>
  )
}
