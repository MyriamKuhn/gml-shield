
import { useEffect, useRef, useState } from 'react'
import { AxiosError } from 'axios'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Divider,
  LinearProgress,
  Paper,
  Stack,
  Typography,
} from '@mui/material'

import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import PlaceOutlinedIcon from '@mui/icons-material/PlaceOutlined'
import FlagOutlinedIcon from '@mui/icons-material/FlagOutlined'
import PersonOutlineRoundedIcon from '@mui/icons-material/PersonOutlineRounded'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import EventOutlinedIcon from '@mui/icons-material/EventOutlined'
import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined'

import {
  cancelMission,
  completeMission,
  getMissions,
  startMission,
  type Mission,
  type MissionStatus,
} from '../../api/missions'

import type { AuthUser } from '../../api/auth'
import MissionAssignForm from './MissionAssignForm'
import MissionEditForm from './MissionEditForm'
import MissionSecretCode from './MissionSecretCode'
import MissionDocumentUpload from './MissionDocumentUpload'
import MissionDocumentsList from './MissionDocumentsList'

const statusLabels: Record<MissionStatus, string> = {
  PENDING: 'En attente',
  IN_PROGRESS: 'En cours',
  COMPLETED: 'Terminée',
  CANCELLED: 'Annulée',
}

const statusColors: Record<
  MissionStatus,
  'default' | 'info' | 'success' | 'error'
> = {
  PENDING: 'default',
  IN_PROGRESS: 'info',
  COMPLETED: 'success',
  CANCELLED: 'error',
}

interface MissionListProps {
  user: AuthUser
  refreshKey?: number
}

/**
 * Display missions accessible to the authenticated user.
 *
 * Refreshes are performed without unmounting the mission cards.
 * Laravel remains responsible for all authorization checks.
 */
export default function MissionList({
  user,
  refreshKey = 0,
}: MissionListProps) {
  const [missions, setMissions] = useState<Mission[]>([])
  const [loading, setLoading] = useState(true)
  const [hasLoaded, setHasLoaded] = useState(false)
  const [error, setError] = useState('')

  const requestId = useRef(0)

  const [startingMissionId, setStartingMissionId] = useState<number | null>(
    null,
  )
  const [completingMissionId, setCompletingMissionId] = useState<number | null>(
    null,
  )
  const [editingMissionId, setEditingMissionId] = useState<number | null>(
    null,
  )
  const [missionToCancel, setMissionToCancel] = useState<Mission | null>(null)
  const [cancellingMissionId, setCancellingMissionId] = useState<
    number | null
  >(null)

  /*
   * Initial load and subsequent refresh signals.
   *
   * An updated refreshKey starts a background fetch.
   * The existing cards remain visible throughout the request.
   */
  useEffect(() => {
    let active = true
    const currentRequest = ++requestId.current

    getMissions()
      .then((response) => {
        if (!active || currentRequest !== requestId.current) return

        setMissions(response.data)
        setError('')
      })
      .catch((err) => {
        if (!active || currentRequest !== requestId.current) return

        if (err instanceof AxiosError && err.response?.status === 401) {
          setError('Votre session a expiré. Veuillez vous reconnecter.')
        } else {
          setError('Impossible de charger les missions.')
        }
      })
      .finally(() => {
        if (!active || currentRequest !== requestId.current) return

        setHasLoaded(true)
        setLoading(false)
      })

    return () => {
      active = false
    }
  }, [refreshKey])

  /**
   * Replace a mission using the API's authoritative response.
   */
  function replaceMission(updatedMission: Mission) {
    // Invalidate any older list fetch that could overwrite this update.
    requestId.current += 1

    setMissions((currentMissions) =>
      currentMissions.map((mission) =>
        mission.id === updatedMission.id ? updatedMission : mission,
      ),
    )
  }

  async function handleStartMission(id: number) {
    if (startingMissionId !== null) return

    setStartingMissionId(id)
    setError('')

    try {
      const updatedMission = await startMission(id)
      replaceMission(updatedMission)
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 401) {
        setError('Votre session a expiré. Veuillez vous reconnecter.')
      } else if (err instanceof AxiosError && err.response?.status === 403) {
        setError("Vous n'êtes pas autorisé à démarrer cette mission.")
      } else if (err instanceof AxiosError && err.response?.status === 409) {
        setError('Cette mission ne peut plus être démarrée.')
      } else {
        setError('Impossible de démarrer la mission.')
      }
    } finally {
      setStartingMissionId(null)
    }
  }

  async function handleCompleteMission(id: number) {
    if (startingMissionId !== null || completingMissionId !== null) return

    setCompletingMissionId(id)
    setError('')

    try {
      const updatedMission = await completeMission(id)
      replaceMission(updatedMission)
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 401) {
        setError('Votre session a expiré. Veuillez vous reconnecter.')
      } else if (err instanceof AxiosError && err.response?.status === 403) {
        setError("Vous n'êtes pas autorisé à terminer cette mission.")
      } else if (err instanceof AxiosError && err.response?.status === 409) {
        setError('Cette mission ne peut plus être terminée.')
      } else {
        setError('Impossible de terminer la mission.')
      }
    } finally {
      setCompletingMissionId(null)
    }
  }

  function handleDeliveryProofUploaded(missionId: number) {
    setMissions((currentMissions) =>
      currentMissions.map((mission) =>
        mission.id === missionId
          ? { ...mission, has_delivery_proof: true }
          : mission,
      ),
    )
  }

  function handleMissionAssigned(updatedMission: Mission) {
    replaceMission(updatedMission)
  }

  function handleMissionUpdated(updatedMission: Mission) {
    replaceMission(updatedMission)
    setEditingMissionId(null)
  }

  async function handleCancelMission() {
    if (!missionToCancel || cancellingMissionId !== null) return

    const missionId = missionToCancel.id

    setCancellingMissionId(missionId)
    setError('')

    try {
      const updatedMission = await cancelMission(missionId)

      replaceMission(updatedMission)
      setMissionToCancel(null)

      if (editingMissionId === missionId) {
        setEditingMissionId(null)
      }
    } catch (err) {
      if (err instanceof AxiosError && err.response?.status === 401) {
        setError('Votre session a expiré. Veuillez vous reconnecter.')
      } else if (err instanceof AxiosError && err.response?.status === 403) {
        setError("Vous n'êtes pas autorisé à annuler cette mission.")
      } else if (err instanceof AxiosError && err.response?.status === 409) {
        setError('Cette mission ne peut plus être annulée.')
      } else {
        setError("Impossible d'annuler la mission.")
      }

      setMissionToCancel(null)
    } finally {
      setCancellingMissionId(null)
    }
  }

  const isInitialLoading = !hasLoaded && loading

  if (isInitialLoading) {
    return (
      <Box
        sx={{
          display: 'flex',
          justifyContent: 'center',
          py: 5,
        }}
      >
        <CircularProgress aria-label="Chargement des missions" />
      </Box>
    )
  }

  return (
    <Stack spacing={3}>
      {/* Heading */}
      <Box
        sx={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: 2,
        }}
      >
        <Box
          sx={{
            display: 'flex',
            alignItems: 'center',
            gap: 2,
          }}
        >
          <Box
            sx={{
              width: 48,
              height: 48,
              borderRadius: 2,
              bgcolor: '#E8F5F2',
              color: '#00897B',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            <LocalShippingOutlinedIcon sx={{ fontSize: 27 }} />
          </Box>

          <Box>
            <Typography
              variant="h6"
              component="h3"
              sx={{ fontWeight: 700 }}
            >
              {user.role === 'ADMIN'
                ? 'Missions — supervision'
                : 'Mes missions'}
            </Typography>

            <Typography variant="body2" color="text.secondary">
              Suivi des transports et de leur avancement.
            </Typography>
          </Box>
        </Box>

        <Chip
          variant="outlined"
          label={`${missions.length} mission${missions.length > 1 ? 's' : ''}`}
          sx={{
            color: '#00897B',
            borderColor: '#B8DFD7',
            fontWeight: 600,
          }}
        />
      </Box>

      {/* Reserve progress-bar height to avoid layout shifts */}
      <Box sx={{ height: 3 }}>
        {loading && <LinearProgress />}
      </Box>

      {error && <Alert severity="error">{error}</Alert>}

      {missions.length === 0 && !error && (
        <Box
          sx={{
            textAlign: 'center',
            py: 5,
            px: 2,
            bgcolor: '#F8FAFC',
            border: '1px dashed #CBD5E1',
            borderRadius: 2.5,
          }}
        >
          <Inventory2OutlinedIcon
            sx={{ fontSize: 36, color: '#94A3B8', mb: 1 }}
          />

          <Typography sx={{ fontWeight: 700 }}>
            Aucune mission pour le moment
          </Typography>

          <Typography variant="body2" color="text.secondary">
            Les missions accessibles à votre compte apparaîtront ici.
          </Typography>
        </Box>
      )}

      {missions.map((mission) => (
        <Paper
          key={mission.id}
          variant="outlined"
          sx={{
            borderRadius: 2.5,
            overflow: 'hidden',
            bgcolor: '#FFFFFF',
            transition: 'border-color 160ms, box-shadow 160ms',
            '&:hover': {
              borderColor: '#B8D9D3',
              boxShadow: '0 8px 24px rgba(16, 38, 63, 0.05)',
            },
          }}
        >
          {/* Card heading */}
          <Box sx={{ p: { xs: 2, sm: 3 }, pb: 2 }}>
            <Box
              sx={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'flex-start',
                gap: 2,
                flexWrap: 'wrap',
              }}
            >
              <Box sx={{ minWidth: 0, flex: 1 }}>
                <Typography
                  variant="caption"
                  sx={{
                    fontWeight: 700,
                    color: 'secondary.main',
                    letterSpacing: '0.08em',
                  }}
                >
                  {mission.reference}
                </Typography>

                <Typography
                  variant="h6"
                  component="h4"
                  sx={{
                    fontWeight: 700,
                    mt: 0.5,
                    overflowWrap: 'anywhere',
                  }}
                >
                  {mission.title}
                </Typography>
              </Box>

              <Chip
                label={statusLabels[mission.status]}
                color={statusColors[mission.status]}
                size="small"
                sx={{ fontWeight: 600 }}
              />
            </Box>

            {/* Route */}
            <Box
              sx={{
                mt: 2.5,
                p: 2,
                bgcolor: '#F7FAFC',
                borderRadius: 2,
                display: 'flex',
                flexDirection: 'column',
                gap: 2,
              }}
            >
              <Box
                sx={{
                  display: 'flex',
                  alignItems: 'flex-start',
                  gap: 1.5,
                }}
              >
                <PlaceOutlinedIcon
                  sx={{ color: '#00897B', fontSize: 21, mt: 0.2 }}
                />

                <Box sx={{ minWidth: 0 }}>
                  <Typography
                    variant="caption"
                    color="text.secondary"
                  >
                    DÉPART
                  </Typography>
                  <Typography sx={{ overflowWrap: 'anywhere' }}>
                    {mission.pickup_address}
                  </Typography>
                </Box>
              </Box>

              <Box
                sx={{
                  display: 'flex',
                  alignItems: 'flex-start',
                  gap: 1.5,
                }}
              >
                <FlagOutlinedIcon
                  sx={{ color: '#1E3A5F', fontSize: 21, mt: 0.2 }}
                />

                <Box sx={{ minWidth: 0 }}>
                  <Typography
                    variant="caption"
                    color="text.secondary"
                  >
                    LIVRAISON
                  </Typography>
                  <Typography sx={{ overflowWrap: 'anywhere' }}>
                    {mission.delivery_address}
                  </Typography>
                </Box>
              </Box>
            </Box>

            {/* Assignment and creator */}
            <Box
              sx={{
                display: 'flex',
                flexWrap: 'wrap',
                gap: 2,
                mt: 2.5,
              }}
            >
              <Box
                sx={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 1,
                }}
              >
                <PersonOutlineRoundedIcon
                  sx={{ color: 'text.secondary', fontSize: 20 }}
                />
                <Typography variant="body2" color="text.secondary">
                  Chauffeur :{' '}
                  <Box
                    component="span"
                    sx={{ fontWeight: 600, color: 'text.primary' }}
                  >
                    {mission.driver?.name ?? 'Non affecté'}
                  </Box>
                </Typography>
              </Box>

              {user.role === 'ADMIN' && (
                <Box
                  sx={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 1,
                  }}
                >
                  <EventOutlinedIcon
                    sx={{ color: 'text.secondary', fontSize: 20 }}
                  />
                  <Typography variant="body2" color="text.secondary">
                    Créée par :{' '}
                    <Box
                      component="span"
                      sx={{ fontWeight: 600, color: 'text.primary' }}
                    >
                      {mission.creator?.name ?? 'Utilisateur indisponible'}
                    </Box>
                  </Typography>
                </Box>
              )}
            </Box>
          </Box>

          <Divider />

          {/* Role-specific actions */}
          <Box sx={{ p: { xs: 2, sm: 3 } }}>
            <Stack spacing={2}>
              {user.role === 'DRIVER' &&
                mission.driver_id === user.id &&
                mission.status === 'IN_PROGRESS' && (
                  <MissionSecretCode
                    key={`${user.id}-${mission.id}`}
                    missionId={mission.id}
                  />
                )}

              {user.role === 'DRIVER' &&
                mission.driver_id === user.id &&
                mission.status === 'IN_PROGRESS' && (
                  <MissionDocumentUpload
                    key={`upload-${mission.id}`}
                    missionId={mission.id}
                    onUploadSuccess={() =>
                      handleDeliveryProofUploaded(mission.id)
                    }
                  />
                )}

              {/* Delivery proofs are visible after mission completion. */}
              {user.role === 'DISPATCHER' &&
                mission.created_by === user.id &&
                mission.status === 'COMPLETED' && (
                  <MissionDocumentsList
                    key={`documents-${mission.id}`}
                    missionId={mission.id}
                  />
                )}

              {(user.role === 'ADMIN' || user.role === 'DISPATCHER') &&
                mission.status === 'PENDING' && (
                  <Stack spacing={2}>
                    {editingMissionId === mission.id ? (
                      <MissionEditForm
                        key={mission.id}
                        mission={mission}
                        onMissionUpdated={handleMissionUpdated}
                        onCancel={() => setEditingMissionId(null)}
                      />
                    ) : (
                      <Box
                        sx={{
                          display: 'flex',
                          justifyContent: 'flex-end',
                        }}
                      >
                        <Button
                          variant="outlined"
                          startIcon={<EditOutlinedIcon />}
                          onClick={() => setEditingMissionId(mission.id)}
                        >
                          Modifier la mission
                        </Button>
                      </Box>
                    )}
                  </Stack>
                )}

              {(user.role === 'ADMIN' || user.role === 'DISPATCHER') &&
                (mission.status === 'PENDING' ||
                  mission.status === 'IN_PROGRESS') && (
                  <Box
                    sx={{
                      display: 'flex',
                      justifyContent: 'flex-end',
                    }}
                  >
                    <Button
                      variant="outlined"
                      color="error"
                      disabled={cancellingMissionId !== null}
                      onClick={() => setMissionToCancel(mission)}
                    >
                      Annuler la mission
                    </Button>
                  </Box>
                )}

              {(user.role === 'ADMIN' || user.role === 'DISPATCHER') &&
                mission.status === 'PENDING' && (
                  <MissionAssignForm
                    mission={mission}
                    onMissionAssigned={handleMissionAssigned}
                  />
                )}

              {user.role === 'DRIVER' &&
                mission.driver_id === user.id &&
                (mission.status === 'PENDING' ||
                  mission.status === 'IN_PROGRESS') && (
                  <Box
                    sx={{
                      display: 'flex',
                      justifyContent: 'flex-end',
                      pt: 1,
                    }}
                  >
                    {mission.status === 'PENDING' && (
                      <Button
                        variant="contained"
                        disabled={
                          startingMissionId !== null ||
                          completingMissionId !== null
                        }
                        loading={startingMissionId === mission.id}
                        onClick={() =>
                          void handleStartMission(mission.id)
                        }
                      >
                        Démarrer la mission
                      </Button>
                    )}

                    {mission.status === 'IN_PROGRESS' && (
                      <Stack spacing={1.5} sx={{ width: '100%' }}>
                        <Box
                          sx={{
                            display: 'flex',
                            justifyContent: 'flex-end',
                          }}
                        >
                          <Button
                            variant="contained"
                            color="success"
                            disabled={
                              startingMissionId !== null ||
                              completingMissionId !== null ||
                              mission.has_delivery_proof !== true
                            }
                            loading={completingMissionId === mission.id}
                            onClick={() =>
                              void handleCompleteMission(mission.id)
                            }
                          >
                            Terminer la mission
                          </Button>
                        </Box>

                        {mission.has_delivery_proof !== true && (
                          <Typography
                            variant="body2"
                            color="text.secondary"
                            sx={{ textAlign: 'right' }}
                          >
                            Déposez une preuve de livraison pour
                            pouvoir terminer la mission.
                          </Typography>
                        )}
                      </Stack>
                    )}
                  </Box>
                )}
            </Stack>
          </Box>
        </Paper>
      ))}

      {/* Cancellation confirmation */}
      <Dialog
        open={missionToCancel !== null}
        onClose={() => {
          if (cancellingMissionId === null) {
            setMissionToCancel(null)
          }
        }}
        aria-labelledby="cancel-mission-dialog-title"
        aria-describedby="cancel-mission-dialog-description"
      >
        <DialogTitle id="cancel-mission-dialog-title">
          Confirmer l'annulation
        </DialogTitle>

        <DialogContent>
          <DialogContentText id="cancel-mission-dialog-description">
            Voulez-vous vraiment annuler la mission{' '}
            <strong>{missionToCancel?.reference}</strong> ?
            Cette opération conservera la mission dans l'historique
            avec le statut « Annulée ».
          </DialogContentText>
        </DialogContent>

        <DialogActions>
          <Button
            onClick={() => setMissionToCancel(null)}
            disabled={cancellingMissionId !== null}
          >
            Retour
          </Button>

          <Button
            variant="contained"
            color="error"
            loading={cancellingMissionId !== null}
            disabled={cancellingMissionId !== null}
            onClick={() => void handleCancelMission()}
          >
            Confirmer l'annulation
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  )
}
