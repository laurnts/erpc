<?php

declare(strict_types=1);

return [
    'form' => [
        'team_name' => [
            'label' => 'Team Name',
        ],
    ],

    'sections' => [
        'update_team_name' => [
            'title' => 'Team Name',
            'description' => 'The team\'s name and owner information.',
        ],
        'pending_team_invitations' => [
            'title' => 'Pending Team Invitations',
            'description' => 'These people have been invited to your team and have been sent an invitation email. They may join the team by accepting the email invitation.',
        ],
        'delete_team' => [
            'title' => 'Delete Team',
            'description' => 'Permanently delete this team.',
            'notice' => 'Once a team is deleted, all of its resources and data will be permanently deleted. Before deleting this team, please download any data or information that you wish to retain.',
        ],
    ],

    'actions' => [
        'save' => 'Save',
        'resend_team_invitation' => 'Resend',
        'cancel_team_invitation' => 'Cancel',
        'delete_team' => 'Delete Team',
    ],

    'notifications' => [
        'save' => [
            'success' => 'Saved.',
        ],
        'team_invitation_sent' => [
            'success' => 'Team invitation sent.',
        ],
        'team_invitation_cancelled' => [
            'success' => 'Team invitation cancelled.',
        ],
        'team_deleted' => [
            'success' => 'Team deleted!',
        ],
    ],

    'validation' => [
        'email_already_invited' => 'This user has already been invited to the team.',
    ],

    'modals' => [
        'delete_team' => [
            'notice' => 'Are you sure you want to delete this team? Once a team is deleted, all of its resources and data will be permanently deleted.',
        ],
    ],

    'edit_team' => 'Edit Team',
];
