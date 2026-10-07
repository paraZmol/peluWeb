import { router } from '@inertiajs/react';
import { ImageUploadButton } from '@/components/form/image-upload-button';
import type { StaffEditor } from '@/components/schedule/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { destroy, store } from '@/routes/staff/photo';

type Props = {
    staff: Pick<
        StaffEditor,
        'id' | 'name' | 'title' | 'photoUrl' | 'hasOwnPhoto' | 'isActive'
    >;
};

/** Who the schedule screen is about, with their photo controls. */
export function StaffHeader({ staff }: Props) {
    const getInitials = useInitials();

    return (
        <header className="flex flex-col gap-3">
            <div className="flex items-center gap-4">
                <Avatar className="size-16">
                    <AvatarImage src={staff.photoUrl ?? undefined} alt="" />
                    <AvatarFallback className="text-lg">
                        {getInitials(staff.name)}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1">
                    <h1 className="text-2xl font-semibold break-words">
                        {staff.name}
                    </h1>
                    <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        {staff.title}
                        {!staff.isActive && (
                            <Badge variant="secondary">No atiende</Badge>
                        )}
                    </p>
                </div>
            </div>

            <div className="flex flex-wrap items-start gap-2">
                <ImageUploadButton
                    route={store(staff.id)}
                    label={staff.hasOwnPhoto ? 'Cambiar foto' : 'Subir foto'}
                />
                {staff.hasOwnPhoto && (
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() =>
                            router.delete(destroy(staff.id), {
                                preserveScroll: true,
                            })
                        }
                    >
                        Quitar foto
                    </Button>
                )}
            </div>
        </header>
    );
}
