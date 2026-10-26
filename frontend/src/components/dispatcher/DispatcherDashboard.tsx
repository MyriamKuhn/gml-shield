
import { useState } from 'react'
import {
  Box,
  Chip,
  Paper,
  Stack,
  Typography,
} from '@mui/material'

import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import AddCircleOutlineRoundedIcon from '@mui/icons-material/AddCircleOutlineRounded'
import RouteOutlinedIcon from '@mui/icons-material/RouteOutlined'
import ShieldOutlinedIcon from '@mui/icons-material/ShieldOutlined'
import ArrowForwardRoundedIcon from '@mui/icons-material/ArrowForwardRounded'

import type { AuthUser } from '../../api/auth'
import MissionCreateForm from '../missions/MissionCreateForm'
import MissionList from '../missions/MissionList'

interface DispatcherDashboardProps {
  user: AuthUser
}

type DispatcherSection = 'missions' | 'create'

export default function DispatcherDashboard({
  user,
}: DispatcherDashboardProps) {
  const [section, setSection] = useState<DispatcherSection>('missions')
  const [missionRefreshKey, setMissionRefreshKey] = useState(0)

  function handleMissionCreated() {
    setMissionRefreshKey((current) => current + 1)
    setSection('missions')
  }

  return (
    <Stack spacing={3}>
      {/* Dispatcher identity */}
      <Paper
        elevation={0}
        sx={{
          position: 'relative',
          overflow: 'hidden',
          borderRadius: 3,
          p: { xs: 3, sm: 4 },
          color: '#FFFFFF',
          background:
            'linear-gradient(135deg, #10263F 0%, #1E3A5F 70%, #17505C 100%)',
        }}
      >
        <Box
          sx={{
            position: 'absolute',
            right: -85,
            bottom: -130,
            width: 250,
            height: 250,
            borderRadius: '50%',
            border: '40px solid rgba(99, 224, 203, 0.08)',
            pointerEvents: 'none',
          }}
        />

        <Stack spacing={3} sx={{ position: 'relative', zIndex: 1 }}>
          <Box
            sx={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              gap: 2,
              flexWrap: 'wrap',
            }}
          >
            <Box
              sx={{
                width: 52,
                height: 52,
                borderRadius: 2,
                bgcolor: 'rgba(99, 224, 203, 0.14)',
                border: '1px solid rgba(99, 224, 203, 0.3)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <RouteOutlinedIcon
                sx={{ fontSize: 30, color: '#63E0CB' }}
              />
            </Box>

            <Chip
              icon={
                <ShieldOutlinedIcon
                  sx={{ color: '#B3F2DF !important' }}
                />
              }
              label="Répartition"
              sx={{
                color: '#B3F2DF',
                bgcolor: 'rgba(0, 137, 123, 0.28)',
                border: '1px solid rgba(99, 224, 203, 0.2)',
                fontWeight: 600,
              }}
            />
          </Box>

          <Box>
            <Typography
              variant="overline"
              sx={{
                color: '#63E0CB',
                fontWeight: 700,
                letterSpacing: '0.14em',
              }}
            >
              GML-SHIELD / RÉPARTITION
            </Typography>

            <Typography
              variant="h4"
              component="h2"
              sx={{
                mt: 1,
                mb: 1.5,
                color: '#FFFFFF',
                fontWeight: 800,
                fontSize: { xs: '1.8rem', sm: '2.2rem' },
              }}
            >
              Gestion des transports
            </Typography>

            <Typography
              sx={{
                maxWidth: 620,
                lineHeight: 1.75,
                color: '#CAD9E6',
              }}
            >
              Préparez les missions, affectez les chauffeurs
              et suivez les livraisons depuis votre espace
              de répartition.
            </Typography>
          </Box>
        </Stack>
      </Paper>

      {/* Navigation */}
      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: {
            xs: '1fr',
            sm: 'repeat(2, minmax(0, 1fr))',
          },
          gap: 2,
        }}
      >
        <Paper
          component="button"
          type="button"
          elevation={0}
          onClick={() => setSection('missions')}
          aria-pressed={section === 'missions'}
          sx={{
            display: 'flex',
            alignItems: 'center',
            textAlign: 'left',
            gap: 2,
            width: '100%',
            p: 2.5,
            borderRadius: 2.5,
            font: 'inherit',
            cursor: 'pointer',
            bgcolor: section === 'missions' ? '#F0FAF8' : '#FFFFFF',
            border: '1px solid',
            borderColor:
              section === 'missions' ? '#00897B' : 'divider',
            '&:hover': {
              borderColor: '#00897B',
              bgcolor: '#F0FAF8',
            },
            '&:focus-visible': {
              outline: '3px solid #80CBC4',
              outlineOffset: 2,
            },
          }}
        >
          <Box
            sx={{
              width: 48,
              height: 48,
              flexShrink: 0,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              borderRadius: 2,
              bgcolor:
                section === 'missions' ? '#00897B' : '#E8F5F2',
              color:
                section === 'missions' ? '#FFFFFF' : '#00897B',
            }}
          >
            <LocalShippingOutlinedIcon sx={{ fontSize: 28 }} />
          </Box>

          <Box sx={{ flex: 1 }}>
            <Typography sx={{ fontWeight: 700 }}>
              Mes missions
            </Typography>

            <Typography variant="body2" color="text.secondary">
              Affectations et suivi
            </Typography>
          </Box>

          <ArrowForwardRoundedIcon
            sx={{ color: 'text.secondary', fontSize: 20 }}
          />
        </Paper>

        <Paper
          component="button"
          type="button"
          elevation={0}
          onClick={() => setSection('create')}
          aria-pressed={section === 'create'}
          sx={{
            display: 'flex',
            alignItems: 'center',
            textAlign: 'left',
            gap: 2,
            width: '100%',
            p: 2.5,
            borderRadius: 2.5,
            font: 'inherit',
            cursor: 'pointer',
            bgcolor: section === 'create' ? '#F0FAF8' : '#FFFFFF',
            border: '1px solid',
            borderColor:
              section === 'create' ? '#00897B' : 'divider',
            '&:hover': {
              borderColor: '#00897B',
              bgcolor: '#F0FAF8',
            },
            '&:focus-visible': {
              outline: '3px solid #80CBC4',
              outlineOffset: 2,
            },
          }}
        >
          <Box
            sx={{
              width: 48,
              height: 48,
              flexShrink: 0,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              borderRadius: 2,
              bgcolor:
                section === 'create' ? '#00897B' : '#E8F5F2',
              color:
                section === 'create' ? '#FFFFFF' : '#00897B',
            }}
          >
            <AddCircleOutlineRoundedIcon sx={{ fontSize: 28 }} />
          </Box>

          <Box sx={{ flex: 1 }}>
            <Typography sx={{ fontWeight: 700 }}>
              Nouvelle mission
            </Typography>

            <Typography variant="body2" color="text.secondary">
              Préparer un transport
            </Typography>
          </Box>

          <ArrowForwardRoundedIcon
            sx={{ color: 'text.secondary', fontSize: 20 }}
          />
        </Paper>
      </Box>

      {/* Selected operational section */}
      <Paper
        variant="outlined"
        sx={{
          p: { xs: 2, sm: 3 },
          borderRadius: 3,
          bgcolor: '#FFFFFF',
          minWidth: 0,
        }}
      >
        {section === 'missions' && (
          <MissionList
            user={user}
            refreshKey={missionRefreshKey}
          />
        )}

        {section === 'create' && (
          <MissionCreateForm
            onMissionCreated={handleMissionCreated}
          />
        )}
      </Paper>

      <Typography
        variant="caption"
        sx={{
          color: 'text.secondary',
          textAlign: 'center',
        }}
      >
        Seules les missions autorisées pour votre compte
        sont accessibles.
      </Typography>
    </Stack>
  )
}
