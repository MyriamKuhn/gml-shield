
import { useState } from 'react'
import {
  Box,
  Button,
  Chip,
  Paper,
  Stack,
  Typography,
} from '@mui/material'

import ShieldOutlinedIcon from '@mui/icons-material/ShieldOutlined'
import PeopleOutlineRoundedIcon from '@mui/icons-material/PeopleOutlineRounded'
import FactCheckOutlinedIcon from '@mui/icons-material/FactCheckOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import MenuBookOutlinedIcon from '@mui/icons-material/MenuBookOutlined'
import ArrowForwardRoundedIcon from '@mui/icons-material/ArrowForwardRounded'
import AdminPanelSettingsOutlinedIcon from '@mui/icons-material/AdminPanelSettingsOutlined'

import type { AuthUser } from '../../api/auth'
import AuditLogList from './AuditLogList'
import UserManagement from './UserManagement'
import MissionList from '../missions/MissionList'
import MissionCreateForm from '../missions/MissionCreateForm'

interface AdminDashboardProps {
  user: AuthUser
}

type AdminSection = 'audit' | 'missions' | 'users'

const adminSections = [
  {
    id: 'users',
    title: 'Utilisateurs',
    description: 'Comptes et autorisations',
    icon: <PeopleOutlineRoundedIcon sx={{ fontSize: 29 }} />,
  },
  {
    id: 'audit',
    title: "Journal d'audit",
    description: 'Traçabilité et sécurité',
    icon: <FactCheckOutlinedIcon sx={{ fontSize: 29 }} />,
  },
  {
    id: 'missions',
    title: 'Missions',
    description: 'Supervision opérationnelle',
    icon: <LocalShippingOutlinedIcon sx={{ fontSize: 29 }} />,
  },
] as const

export default function AdminDashboard({
  user,
}: AdminDashboardProps) {
  const [section, setSection] = useState<AdminSection>('audit')
  const [missionRefreshKey, setMissionRefreshKey] = useState(0)

  const apiDocumentationUrl = `${import.meta.env.VITE_API_URL || 'http://localhost:8000'
    }/docs/api`

  return (
    <Stack spacing={3}>
      {/* Administrative identity */}
      <Paper
        elevation={0}
        sx={{
          position: 'relative',
          overflow: 'hidden',
          borderRadius: 3,
          background:
            'linear-gradient(135deg, #10263F 0%, #1E3A5F 70%, #17505C 100%)',
          color: '#FFFFFF',
          p: { xs: 3, sm: 4 },
        }}
      >
        <Box
          sx={{
            position: 'absolute',
            width: 240,
            height: 240,
            borderRadius: '50%',
            border: '40px solid rgba(79, 224, 198, 0.08)',
            right: -90,
            bottom: -130,
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
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                bgcolor: 'rgba(99, 224, 203, 0.14)',
                border: '1px solid rgba(99, 224, 203, 0.3)',
              }}
            >
              <ShieldOutlinedIcon
                sx={{ color: '#63E0CB', fontSize: 30 }}
              />
            </Box>

            <Chip
              icon={
                <AdminPanelSettingsOutlinedIcon
                  sx={{ color: '#B3F2DF !important' }}
                />
              }
              label="Administration"
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
              GML-SHIELD / ADMINISTRATION
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
              Centre de supervision
            </Typography>

            <Typography
              sx={{
                color: '#CAD9E6',
                lineHeight: 1.75,
                maxWidth: 620,
              }}
            >
              Pilotez les accès, supervisez les missions et
              consultez les événements de sécurité depuis
              votre espace d'administration.
            </Typography>
          </Box>
        </Stack>
      </Paper>

      {/* Main navigation */}
      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: {
            xs: '1fr',
            sm: 'repeat(2, minmax(0, 1fr))',
            md: 'repeat(3, minmax(0, 1fr))',
          },
          gap: 2,
        }}
      >
        {adminSections.map((item) => {
          const active = section === item.id

          return (
            <Paper
              key={item.id}
              component="button"
              type="button"
              elevation={0}
              onClick={() => setSection(item.id)}
              aria-pressed={active}
              sx={{
                display: 'flex',
                alignItems: 'flex-start',
                gap: 2,
                textAlign: 'left',
                width: '100%',
                p: 2.5,
                borderRadius: 2.5,
                cursor: 'pointer',
                font: 'inherit',
                color: 'text.primary',
                bgcolor: active ? '#F0FAF8' : '#FFFFFF',
                border: '1px solid',
                borderColor: active ? '#00897B' : 'divider',
                boxShadow: active
                  ? '0 8px 24px rgba(0, 137, 123, 0.08)'
                  : 'none',
                transition: 'background-color 160ms, border-color 160ms, box-shadow 160ms',
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
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  width: 48,
                  height: 48,
                  flexShrink: 0,
                  borderRadius: 2,
                  color: active ? '#FFFFFF' : '#00897B',
                  bgcolor: active ? '#00897B' : '#E8F5F2',
                }}
              >
                {item.icon}
              </Box>

              <Box sx={{ minWidth: 0, flex: 1 }}>
                <Typography
                  sx={{
                    fontWeight: 700,
                    fontSize: '0.98rem',
                    mb: 0.5,
                  }}
                >
                  {item.title}
                </Typography>

                <Typography
                  variant="body2"
                  sx={{
                    color: 'text.secondary',
                    lineHeight: 1.5,
                  }}
                >
                  {item.description}
                </Typography>
              </Box>
            </Paper>
          )
        })}
      </Box>

      {/* API documentation shortcut */}
      <Paper
        variant="outlined"
        sx={{
          borderRadius: 2.5,
          p: { xs: 2, sm: 2.5 },
          bgcolor: '#FFFFFF',
        }}
      >
        <Box
          sx={{
            display: 'flex',
            alignItems: { xs: 'flex-start', sm: 'center' },
            justifyContent: 'space-between',
            gap: 2,
            flexDirection: { xs: 'column', sm: 'row' },
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
                display: 'flex',
                justifyContent: 'center',
                alignItems: 'center',
                width: 44,
                height: 44,
                borderRadius: 2,
                bgcolor: '#EDF3FA',
                color: '#1E3A5F',
                flexShrink: 0,
              }}
            >
              <MenuBookOutlinedIcon />
            </Box>

            <Box>
              <Typography sx={{ fontWeight: 700 }}>
                Documentation technique
              </Typography>

              <Typography
                variant="body2"
                color="text.secondary"
              >
                Explorer les endpoints de l'API GML-SHIELD.
              </Typography>
            </Box>
          </Box>

          <Button
            variant="outlined"
            endIcon={<ArrowForwardRoundedIcon />}
            href={apiDocumentationUrl}
            target="_blank"
            rel="noopener noreferrer"
            sx={{ flexShrink: 0 }}
          >
            Ouvrir Swagger
          </Button>
        </Box>
      </Paper>

      {/* Selected section */}
      <Paper
        variant="outlined"
        sx={{
          borderRadius: 3,
          p: { xs: 2, sm: 3 },
          bgcolor: '#FFFFFF',
          minWidth: 0,
        }}
      >
        {section === 'audit' && <AuditLogList />}

        {section === 'users' && <UserManagement />}

        {section === 'missions' && (
          <Stack spacing={3}>
            <Box>
              <Typography variant="h6" component="h3">
                Supervision des missions
              </Typography>

              <Typography
                variant="body2"
                color="text.secondary"
                sx={{ mt: 1 }}
              >
                Gestion exceptionnelle des missions selon
                les autorisations accordées par Laravel.
              </Typography>
            </Box>

            <MissionCreateForm
              onMissionCreated={() => {
                setMissionRefreshKey((current) => current + 1)
              }}
            />

            <MissionList
              refreshKey={missionRefreshKey}
              user={user}
            />
          </Stack>
        )}
      </Paper>
    </Stack>
  )
}
