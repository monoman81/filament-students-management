<?php

namespace App\Filament\Resources;

use App\Exports\StudentsExporter;
use App\Filament\Resources\StudentResource\Pages;
use App\Models\Classes;
use App\Models\Section;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Maatwebsite\Excel\Excel;
use PHPUnit\Util\Filter;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationGroup = "Academic Management";

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('class_id')
                    ->relationship('class', 'name')
                    ->live(),
                Select::make('section_id')
                    ->options(function (Get $get) {
                        $classId = $get('class_id');
                        if ($classId) {
                            return Section::where(['class_id' => $classId])->get()->pluck('name', 'id');
                        }
                        return [];
                    }),
                TextInput::make('name')
                    ->required()
                    ->autofocus(),
                TextInput::make('email')
                    ->required()
                    ->unique()
                    ->email(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('class.name')
                    ->badge()->searchable(),
                TextColumn::make('section.name')
                    ->badge()->searchable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('class-section-filter')->form([
                    Select::make('class_id')
                        ->label('Filter By Class')
                        ->placeholder('Select a Class')
                        ->live()
                        ->options(
                            Classes::all()->pluck('name', 'id')->toArray()
                        ),
                    Select::make('section_id')
                        ->label('Filter By Section')
                        ->placeholder('Select a Section')
                        ->options(function (Get $get) {
                            $classId = $get('class_id');
                            if ($classId) {
                                return Section::where(['class_id' => $classId])->get()->pluck('name', 'id');
                            }
                            return [];
                        }),
                ])->query(function (Builder $query, array $data): Builder {
                    return $query->when($data['class_id'], function(Builder $query) use ($data): Builder  {
                        return $query->where('class_id', $data['class_id']);
                    })->when($data['section_id'], function(Builder $query) use ($data): Builder  {
                        return $query->where('section_id', $data['section_id']);
                    });
                })
            ])
            ->actions([
                Action::make('download_pdf')->url(function(Student $student): string {
                    return route('student.pdf.generate', $student);
                }),
                Action::make('qrCode')->url(function(Student $student) {
                    return static::getUrl('qrCode', ['record' => $student]);
                }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('export')
                        ->label('Export to Excel')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function (Collection $records) {
                            return Excel::download(new StudentsExporter($records), 'students.xlsx');
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
            'qrCode' => Pages\GenerateQRCode::route('/{record}/qrCode'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }
}
