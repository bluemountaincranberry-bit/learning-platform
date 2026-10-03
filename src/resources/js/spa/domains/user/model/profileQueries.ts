import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { profileApi } from '../api/profileApi';
import { profileQueryKeys } from './profileQueryKeys';

export type ProfileUpdatePayload = Parameters<typeof profileApi.updateProfile>[0];

export function useProfileQuery() {
    return useQuery({
        queryKey: profileQueryKeys.profile(),
        queryFn: profileApi.getProfile,
    });
}

export function useUpdateProfileMutation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: ProfileUpdatePayload) => profileApi.updateProfile(payload),
        onSuccess: (response) => {
            queryClient.setQueryData(profileQueryKeys.profile(), response);
        },
    });
}
