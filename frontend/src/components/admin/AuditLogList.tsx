import { useEffect, useState } from 'react'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { AxiosError } from 'axios'
import {
  getAuditLogs,
  type AuditLogPage,
} from '../../api/auditLogs'

export default function AuditLogList() {
  const [page, setPage] = useState(1)
  const [auditPage, setAuditPage] = useState<AuditLogPage | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true

    async function loadAuditLogs() {
      setLoading(true)
      setError('')

      try {
        const result = await getAuditLogs(page)

        if (active) {
          setAuditPage(result)
        }
      } catch (err) {
        if (!active) return

        if (err instanceof AxiosError && err.response?.status === 403) {
          setError("Vous n'êtes pas autorisé à consulter le journal d'audit.")
        } else if (err instanceof AxiosError && err.response?.status === 401) {
          setError('Votre session a expiré. Veuillez vous reconnecter.')
        } else {
          setError('Impossible de charger le journal d’audit.')
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void loadAuditLogs()

    return () => {
      active = false
    }
  }, [page])

  return (
    <Stack spacing={2}>
      <Typography variant="h6" component="h2">
        Journal d'audit
      </Typography>

      <Typography variant="body2" color="text.secondary">
        Historique des événements de sécurité et des opérations.
        Consultation réservée aux administrateurs.
      </Typography>

      {error && <Alert severity="error">{error}</Alert>}

      {loading ? (
        <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
          <CircularProgress aria-label="Chargement du journal d’audit" />
        </Box>
      ) : (
        !error && (
          <>
            {auditPage?.data.length === 0 ? (
              <Alert severity="info">Aucun événement à afficher.</Alert>
            ) : (
              <TableContainer component={Paper} variant="outlined">
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Date</TableCell>
                      <TableCell>Action</TableCell>
                      <TableCell>Utilisateur</TableCell>
                      <TableCell>Adresse IP</TableCell>
                    </TableRow>
                  </TableHead>

                  <TableBody>
                    {auditPage?.data.map((entry) => (
                      <TableRow key={entry.id}>
                        <TableCell>
                          {new Date(entry.timestamp).toLocaleString('fr-FR')}
                        </TableCell>
                        <TableCell sx={{ overflowWrap: 'anywhere' }}>
                          {entry.action}
                        </TableCell>
                        <TableCell>
                          {entry.user_id ?? 'Non authentifié'}
                        </TableCell>
                        <TableCell>
                          {entry.ip_address ?? 'Non disponible'}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            )}

            {auditPage && (
              <Stack
                direction="row"
                spacing={2}
                sx={{
                  alignItems: 'center',
                  justifyContent: 'space-between',
                }}
              >
                <Button
                  variant="outlined"
                  disabled={page <= 1}
                  onClick={() => setPage((current) => current - 1)}
                >
                  Précédent
                </Button>

                <Typography variant="body2">
                  Page {auditPage.current_page} / {auditPage.last_page}
                </Typography>

                <Button
                  variant="outlined"
                  disabled={page >= auditPage.last_page}
                  onClick={() => setPage((current) => current + 1)}
                >
                  Suivant
                </Button>
              </Stack>
            )}
          </>
        )
      )}
    </Stack>
  )
}