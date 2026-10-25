import { useEffect, useState } from 'react'
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
  Paper,
  Stack,
  Typography,
} from '@mui/material'

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
}

/**
 * Display missions accessible to the authenticated user.
 */
export default function MissionList({ user }: MissionListProps) {
  const [missions, setMissions] = useState<Mission[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // Track the mission currently being started to prevent duplicate requests.
  const [startingMissionId, setStartingMissionId] = useState<number | null>(null)

  // Track the mission currently being completed to prevent duplicate requests.
  const [completingMissionId, setCompletingMissionId] = useState<number | null>(null)

  // Track the mission currently being edited.
  const [editingMissionId, setEditingMissionId] = useState<number | null>(null)

  // Track the mission selected for cancellation.
  const [missionToCancel, setMissionToCancel] = useState<Mission | null>(null)

  // Track an ongoing cancellation request.
  const [cancellingMissionId, setCancellingMissionId] = useState<number | null>(null)

  useEffect(() => {
    let active = true

    async function loadMissions() {
      try {
        const response = await getMissions()

        if (active) {
          setMissions(response.data)
        }
      } catch (err) {
        if (!active) return

        if (err instanceof AxiosError && err.response?.status === 401) {
          setError('Votre session a expiré. Veuillez vous reconnecter.')
        } else {
          setError('Impossible de charger les missions.')
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void loadMissions()

    return () => {
      active = false
    }
  }, [])

  /**
   * Start a pending mission assigned to the authenticated driver.
   *
   * Update the mission in local state after a successful API response
   * to refresh its status without reloading the page.
   */
  async function handleStartMission(id: number) {
    if (startingMissionId !== null) return

    setStartingMissionId(id)
    setError('')

    try {
      const updatedMission = await startMission(id)

      setMissions((currentMissions) =>
        currentMissions.map((mission) =>
          mission.id === id ? updatedMission : mission
        )
      )
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

  /**
   * Complete an in-progress mission assigned to the authenticated driver.
   *
   * Update the mission in local state after a successful API response
   * to refresh its status without reloading the page.
   */
  async function handleCompleteMission(id: number) {
    if (startingMissionId !== null || completingMissionId !== null) return

    setCompletingMissionId(id)
    setError('')

    try {
      const updatedMission = await completeMission(id)

      setMissions((currentMissions) =>
        currentMissions.map((mission) =>
          mission.id === id ? updatedMission : mission
        )
      )
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

  /**
   * Update the mission list after a successful driver assignment.
   *
   * Replace the updated mission in local state without reloading
   * the entire list from the Laravel API.
   *
   * @param updatedMission - The mission returned by the assignment API.
   */
  function handleMissionAssigned(updatedMission: Mission) {
    setMissions((currentMissions) =>
      currentMissions.map((mission) =>
        mission.id === updatedMission.id ? updatedMission : mission
      )
    )
  }

  /**
   * Update the mission list after a successful edit.
   *
   * Replace the updated mission in local state and close
   * the edit form without reloading the page.
   *
   * @param updatedMission - The mission returned by the update API.
   */
  function handleMissionUpdated(updatedMission: Mission) {
    setMissions((currentMissions) =>
      currentMissions.map((mission) =>
        mission.id === updatedMission.id ? updatedMission : mission
      )
    )

    setEditingMissionId(null)
  }

  /**
   * Cancel a pending or in-progress mission after confirmation.
   *
   * The Laravel API validates the user's permissions and applies
   * the CANCELLED status while preserving the mission record.
   *
   * Update the mission in local state after a successful response.
   */
  async function handleCancelMission() {
    if (!missionToCancel || cancellingMissionId !== null) return

    const missionId = missionToCancel.id

    setCancellingMissionId(missionId)
    setError('')

    try {
      const updatedMission = await cancelMission(missionId)

      setMissions((currentMissions) =>
        currentMissions.map((mission) =>
          mission.id === missionId ? updatedMission : mission
        )
      )

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

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
        <CircularProgress aria-label="Chargement des missions" />
      </Box>
    )
  }

  return (
    <Stack spacing={2}>
      <Typography variant="h6" component="h2">
        Mes missions
      </Typography>

      {error && <Alert severity="error">{error}</Alert>}

      {!error && missions.length === 0 && (
        <Alert severity="info">
          Aucune mission à afficher pour le moment.
        </Alert>
      )}

      {!error &&
        missions.map((mission) => (
          <Paper
            key={mission.id}
            variant="outlined"
            sx={{ p: 2.5, borderRadius: 2 }}
          >
            <Stack spacing={1.5}>
              <Box
                sx={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  flexWrap: 'wrap',
                  gap: 1,
                }}
              >
                <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
                  {mission.title}
                </Typography>

                <Chip
                  label={statusLabels[mission.status]}
                  color={statusColors[mission.status]}
                  size="small"
                />
              </Box>

              <Typography variant="body2" color="text.secondary">
                Référence : {mission.reference}
              </Typography>

              <Typography variant="body2">
                Départ : {mission.pickup_address}
              </Typography>

              <Typography variant="body2">
                Arrivée : {mission.delivery_address}
              </Typography>

              <Typography variant="body2" color="text.secondary">
                Chauffeur : {mission.driver?.name ?? 'Non affecté'}
              </Typography>

              {user.role === 'DRIVER' &&
                mission.driver_id === user.id &&
                mission.status === 'IN_PROGRESS' && (
                  <MissionSecretCode
                    key={`${user.id}-${mission.id}`}
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
                      <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button
                          variant="outlined"
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
                  <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
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

              {(
                user.role === 'ADMIN' ||
                user.role === 'DISPATCHER'
              ) &&
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
                  <Box sx={{ display: 'flex', justifyContent: 'flex-end', pt: 1 }}>
                    {mission.status === 'PENDING' && (
                      <Button
                        variant="contained"
                        disabled={
                          startingMissionId !== null ||
                          completingMissionId !== null
                        }
                        loading={startingMissionId === mission.id}
                        onClick={() => void handleStartMission(mission.id)}
                      >
                        Démarrer la mission
                      </Button>
                    )}

                    {mission.status === 'IN_PROGRESS' && (
                      <Button
                        variant="contained"
                        color="success"
                        disabled={
                          startingMissionId !== null ||
                          completingMissionId !== null
                        }
                        loading={completingMissionId === mission.id}
                        onClick={() => void handleCompleteMission(mission.id)}
                      >
                        Terminer la mission
                      </Button>
                    )}
                  </Box>
                )}
            </Stack>
          </Paper>
        ))}

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