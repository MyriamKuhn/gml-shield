
import { useEffect, useState } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  LinearProgress,
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

import {
  getAuditLogs,
  type AuditLogPage,
} from '../../api/auditLogs'

import ChevronLeftRoundedIcon from '@mui/icons-material/ChevronLeftRounded'
import ChevronRightRoundedIcon from '@mui/icons-material/ChevronRightRounded'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'

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
        } else if (
          err instanceof AxiosError &&
          err.response?.status === 401
        ) {
          setError('Votre session a expiré. Veuillez vous reconnecter.')
        } else {
          setError("Impossible de charger le journal d'audit.")
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

  const isInitialLoading = loading && auditPage === null

  return (
    <Stack spacing={3}>
      {/* Section heading */}
      <Box
        sx={{
          display: 'flex',
          alignItems: 'center',
          gap: 2,
        }}
      >
        <Box
          sx={{
            width: 46,
            height: 46,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            bgcolor: '#E8F5F2',
            color: '#00897B',
            borderRadius: 2,
            flexShrink: 0,
          }}
        >
          <HistoryOutlinedIcon />
        </Box>

        <Box>
          <Typography variant="h6" component="h3" sx={{ fontWeight: 700 }}>
            Journal d'audit
          </Typography>

          <Typography variant="body2" color="text.secondary">
            Historique des événements de sécurité et des opérations.
          </Typography>
        </Box>
      </Box>

      {error && <Alert severity="error">{error}</Alert>}

      {/* Full spinner only on the first load */}
      {isInitialLoading ? (
        <Box
          sx={{
            display: 'flex',
            justifyContent: 'center',
            py: 6,
          }}
        >
          <CircularProgress aria-label="Chargement du journal d'audit" />
        </Box>
      ) : (
        <Stack spacing={2}>
          {/* Keep the table visible during pagination */}
          <Paper
            variant="outlined"
            sx={{
              overflow: 'hidden',
              borderRadius: 2.5,
              position: 'relative',
            }}
          >
            <Box sx={{ height: 3 }}>
              {loading && <LinearProgress />}
            </Box>

            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow sx={{ bgcolor: '#F4F7FB' }}>
                    <TableCell sx={{ fontWeight: 700, py: 2 }}>
                      Date
                    </TableCell>
                    <TableCell sx={{ fontWeight: 700 }}>
                      Action
                    </TableCell>
                    <TableCell sx={{ fontWeight: 700 }}>
                      Utilisateur
                    </TableCell>
                    <TableCell sx={{ fontWeight: 700 }}>
                      Adresse IP
                    </TableCell>
                  </TableRow>
                </TableHead>

                <TableBody>
                  {auditPage?.data.map((entry) => (
                    <TableRow
                      key={entry.id}
                      hover
                      sx={{
                        '&:last-child td': {
                          borderBottom: 0,
                        },
                      }}
                    >
                      <TableCell
                        sx={{
                          whiteSpace: 'nowrap',
                          color: 'text.secondary',
                        }}
                      >
                        {new Date(entry.timestamp).toLocaleString('fr-FR')}
                      </TableCell>

                      <TableCell
                        sx={{
                          fontWeight: 500,
                          overflowWrap: 'anywhere',
                        }}
                      >
                        {entry.action}
                      </TableCell>

                      <TableCell>
                        {entry.user_id ?? 'Non authentifié'}
                      </TableCell>

                      <TableCell sx={{ whiteSpace: 'nowrap' }}>
                        {entry.ip_address ?? 'Non disponible'}
                      </TableCell>
                    </TableRow>
                  ))}

                  {auditPage?.data.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={4}>
                        <Typography
                          color="text.secondary"
                          sx={{
                            textAlign: 'center',
                            py: 4,
                          }}
                        >
                          Aucun événement à afficher.
                        </Typography>
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </TableContainer>
          </Paper>

          {/* Pagination */}
          {auditPage && (
            <Box
              sx={{
                display: 'flex',
                flexDirection: { xs: 'column', sm: 'row' },
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 2,
              }}
            >
              <Typography variant="body2" color="text.secondary">
                Page {auditPage.current_page} sur {auditPage.last_page}
              </Typography>

              <Stack direction="row" spacing={1.5}>
                <Button
                  variant="outlined"
                  startIcon={<ChevronLeftRoundedIcon />}
                  disabled={loading || page <= 1}
                  onClick={() => setPage((current) => current - 1)}
                >
                  Précédent
                </Button>

                <Button
                  variant="outlined"
                  endIcon={<ChevronRightRoundedIcon />}
                  disabled={
                    loading ||
                    page >= auditPage.last_page
                  }
                  onClick={() => setPage((current) => current + 1)}
                >
                  Suivant
                </Button>
              </Stack>
            </Box>
          )}
        </Stack>
      )}
    </Stack>
  )
}
